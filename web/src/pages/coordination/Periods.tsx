import { FormEvent, useState } from 'react';
import { apiErrorMessage } from '../../api/client';
import { useCreatePeriod, useDeletePeriod, useMilestones, usePeriods, useUpdatePeriod } from '../../api/hooks';
import { Milestone, SubmissionPeriod } from '../../api/types';
import { Drawer } from '../../components/Drawer';
import { EmptyState } from '../../components/EmptyState';
import { ClipboardIcon, DocumentIcon, PlusIcon } from '../../components/icons';
import { toast } from '../../components/Toaster';
import { formatDate } from '../../utils/dates';
import { CoordHead } from './CoordinationLayout';
import { Milestones } from './Milestones';
import { dayNumber, formatDayOnly, plural, relativeDays, timelineWindow, todayNumber } from './schedule';

type PeriodType = SubmissionPeriod['type'];

const TYPE_LABELS: Record<PeriodType, string> = { SUBMISSION: 'Submissão', EVALUATION: 'Avaliação' };
const TYPE_HINTS: Record<PeriodType, string> = {
  SUBMISSION: 'Alunos enviam e reenviam o PDF do TCC.',
  EVALUATION: 'A banca registra os pareceres.',
};

type Phase = 'active' | 'upcoming' | 'past';

// A API diz se o prazo está aberto agora (no relógio do servidor); o resto vem das datas.
function phaseOf(p: SubmissionPeriod, today: number): Phase {
  if (p.active) return 'active';
  return dayNumber(p.startDate) > today ? 'upcoming' : 'past';
}

const PHASE_ORDER: Record<Phase, number> = { active: 0, upcoming: 1, past: 2 };

export function Periods() {
  const { data } = usePeriods();
  const periods = Array.isArray(data) ? data : [];
  const { data: milestoneData } = useMilestones();
  const milestones = Array.isArray(milestoneData) ? milestoneData : [];
  const deletePeriod = useDeletePeriod();
  const today = todayNumber();

  const [form, setForm] = useState<{ period: SubmissionPeriod | null } | null>(null);
  const [confirmingDeleteId, setConfirmingDeleteId] = useState<string | null>(null);

  async function handleDelete(p: SubmissionPeriod) {
    await deletePeriod.mutateAsync(p.id);
    setConfirmingDeleteId(null);
    toast('Prazo excluído.');
  }

  const sorted = [...periods].sort((a, b) => {
    const pa = phaseOf(a, today);
    const pb = phaseOf(b, today);
    if (pa !== pb) return PHASE_ORDER[pa] - PHASE_ORDER[pb];
    const diff = dayNumber(a.startDate) - dayNumber(b.startDate);
    return pa === 'past' ? -diff : diff;
  });

  return (
    <div>
      <CoordHead
        title="Prazos"
        actions={
          <button type="button" onClick={() => setForm({ period: null })}>
            <PlusIcon />
            Novo prazo
          </button>
        }
      >
        Quando os alunos podem enviar o TCC, quando a banca avalia e quais entregas aparecem no cronograma.
      </CoordHead>

      <Timeline periods={periods} milestones={milestones} today={today} />

      <div className="coord-split">
        <section className="card">
          <div className="section-head">
            <h2>Janelas de envio e avaliação</h2>
            <span className="count-pill">{periods.length}</span>
          </div>
          {!periods.length && (
            <EmptyState title="Nenhum prazo cadastrado.">Sem um prazo de submissão aberto, os alunos não conseguem enviar o TCC.</EmptyState>
          )}
          <ul className="period-list">
            {sorted.map((p) => {
              const phase = phaseOf(p, today);
              const start = dayNumber(p.startDate);
              const end = dayNumber(p.endDate);
              const total = Math.max(1, end - start);
              const elapsed = Math.min(100, Math.max(0, ((today - start) / total) * 100));
              return (
                <li key={p.id} className={`period period-${phase}`}>
                  <span className={`period-icon period-icon-${p.type.toLowerCase()}`} aria-hidden="true">
                    {p.type === 'SUBMISSION' ? <DocumentIcon /> : <ClipboardIcon />}
                  </span>
                  <div className="period-body">
                    <div className="period-top">
                      <span className="period-type">{TYPE_LABELS[p.type]}</span>
                      {phase === 'active' && <span className="status-badge status-APPROVED">Ativo agora</span>}
                      {phase === 'upcoming' && <span className="period-phase">Começa {relativeDays(start - today)}</span>}
                      {phase === 'past' && <span className="period-phase">Encerrado</span>}
                    </div>
                    <h3>{p.label}</h3>
                    <p className="period-dates">
                      {formatDayOnly(p.startDate)} até {formatDayOnly(p.endDate)}
                    </p>
                    {phase === 'active' && (
                      <div className="period-progress">
                        <div className="period-progress-bar" aria-hidden="true">
                          <span style={{ width: `${elapsed}%` }} />
                        </div>
                        <span>Termina {relativeDays(end - today)}</span>
                      </div>
                    )}
                  </div>
                  {confirmingDeleteId === p.id ? (
                    <div className="row-actions confirming">
                      <span>Excluir este prazo?</span>
                      <button type="button" className="danger solid small" onClick={() => handleDelete(p)}>
                        Confirmar
                      </button>
                      <button type="button" className="secondary small" onClick={() => setConfirmingDeleteId(null)}>
                        Cancelar
                      </button>
                    </div>
                  ) : (
                    <div className="row-actions">
                      <button type="button" className="secondary small" onClick={() => setForm({ period: p })}>
                        Editar
                      </button>
                      <button type="button" className="ghost-danger small" onClick={() => setConfirmingDeleteId(p.id)}>
                        Excluir
                      </button>
                    </div>
                  )}
                </li>
              );
            })}
          </ul>
        </section>

        <Milestones />
      </div>

      <Drawer
        open={form !== null}
        title={form?.period ? 'Editar prazo' : 'Novo prazo'}
        description="O prazo vale para todos os TCCs do semestre."
        onClose={() => setForm(null)}
      >
        {form && <PeriodForm key={form.period?.id ?? 'new'} period={form.period} onDone={() => setForm(null)} />}
      </Drawer>
    </div>
  );
}

function PeriodForm({ period, onDone }: { period: SubmissionPeriod | null; onDone: () => void }) {
  const createPeriod = useCreatePeriod();
  const updatePeriod = useUpdatePeriod();
  const [type, setType] = useState<PeriodType>(period?.type ?? 'SUBMISSION');
  const [label, setLabel] = useState(period?.label ?? '');
  const [startDate, setStartDate] = useState(period?.startDate.slice(0, 10) ?? '');
  const [endDate, setEndDate] = useState(period?.endDate.slice(0, 10) ?? '');
  const [error, setError] = useState<string | null>(null);

  const days = startDate && endDate ? dayNumber(endDate) - dayNumber(startDate) + 1 : null;

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      if (period) {
        await updatePeriod.mutateAsync({ id: period.id, data: { type, label, startDate, endDate } });
      } else {
        await createPeriod.mutateAsync({ type, label, startDate, endDate });
      }
      toast(period ? 'Prazo atualizado.' : 'Prazo criado.');
      onDone();
    } catch (err) {
      setError(apiErrorMessage(err, 'Não foi possível salvar o prazo'));
    }
  }

  return (
    <form onSubmit={handleSubmit} className="drawer-form">
      <fieldset className="choice-cards">
        <legend>Tipo</legend>
        {(['SUBMISSION', 'EVALUATION'] as PeriodType[]).map((t) => (
          <label key={t} className={type === t ? 'checked' : undefined}>
            <input type="radio" name="period-type" value={t} checked={type === t} onChange={() => setType(t)} />
            <span className={`period-icon period-icon-${t.toLowerCase()}`} aria-hidden="true">
              {t === 'SUBMISSION' ? <DocumentIcon /> : <ClipboardIcon />}
            </span>
            <span>
              <strong>{TYPE_LABELS[t]}</strong>
              <small>{TYPE_HINTS[t]}</small>
            </span>
          </label>
        ))}
      </fieldset>
      <div className="form-group">
        <label htmlFor="period-label">Rótulo (ex: 2026/1)</label>
        <input id="period-label" value={label} onChange={(e) => setLabel(e.target.value)} required />
      </div>
      <div className="form-row form-row-2">
        <div className="form-group">
          <label htmlFor="period-start">Início</label>
          <input id="period-start" type="date" value={startDate} onChange={(e) => setStartDate(e.target.value)} required aria-describedby="period-start-preview" />
          <p id="period-start-preview" className="field-hint">
            {startDate ? formatDate(startDate) : 'dd/mm/aaaa'}
          </p>
        </div>
        <div className="form-group">
          <label htmlFor="period-end">Fim</label>
          <input id="period-end" type="date" value={endDate} onChange={(e) => setEndDate(e.target.value)} required aria-describedby="period-end-preview" />
          <p id="period-end-preview" className="field-hint">
            {endDate ? formatDate(endDate) : 'dd/mm/aaaa'}
          </p>
        </div>
      </div>
      {days !== null && days > 0 && <p className="drawer-summary">{plural(days, 'dia', 'dias')} de prazo, contando o primeiro e o último.</p>}
      {error && (
        <p className="error-text" role="alert">
          {error}
        </p>
      )}
      <div className="drawer-actions">
        <button type="button" className="secondary" onClick={onDone}>
          Cancelar
        </button>
        <button type="submit" disabled={createPeriod.isPending || updatePeriod.isPending}>
          {period ? 'Salvar alterações' : 'Criar prazo'}
        </button>
      </div>
    </form>
  );
}

/**
 * Linha do tempo do semestre: uma faixa para cada tipo de prazo e uma para as
 * etapas do cronograma, com o dia de hoje marcado. É um resumo visual; os
 * mesmos dados estão nas listas logo abaixo.
 */
function Timeline({ periods, milestones, today }: { periods: SubmissionPeriod[]; milestones: Milestone[]; today: number }) {
  if (!periods.length && !milestones.length) return null;

  const days = [...periods.flatMap((p) => [dayNumber(p.startDate), dayNumber(p.endDate)]), ...milestones.map((m) => dayNumber(m.dueDate))];
  const { start, end, months } = timelineWindow(days, today);
  const span = end - start;
  const pos = (d: number) => ((Math.min(Math.max(d, start), end) - start) / span) * 100;

  const lanes: { type: PeriodType; label: string }[] = [
    { type: 'SUBMISSION', label: 'Submissão' },
    { type: 'EVALUATION', label: 'Avaliação' },
  ];

  const visibleMilestones = milestones.filter((m) => {
    const d = dayNumber(m.dueDate);
    return d >= start && d <= end;
  });

  return (
    <section className="card timeline-card" aria-label="Linha do tempo do semestre">
      <div className="section-head">
        <h2>Linha do tempo</h2>
        <ul className="timeline-legend" aria-hidden="true">
          <li>
            <span className="swatch swatch-submission" />
            Submissão
          </li>
          <li>
            <span className="swatch swatch-evaluation" />
            Avaliação
          </li>
          <li>
            <span className="swatch swatch-milestone" />
            Etapa
          </li>
        </ul>
      </div>

      <div className="timeline-scroll">
        <div className="timeline" aria-hidden="true">
          <div className="timeline-labels">
            <span />
            {lanes.map((lane) => (
              <span key={lane.type}>{lane.label}</span>
            ))}
            <span>Cronograma</span>
          </div>

          <div className="timeline-plot">
            {months.map((m) => (
              <span key={m.day} className="timeline-gridline" style={{ left: `${pos(m.day)}%` }} />
            ))}

            <div className="timeline-axis">
              {months.map((m) => (
                <span key={m.day} style={{ left: `${pos(m.day)}%` }}>
                  {m.label}
                  {m.year && <small> {m.year}</small>}
                </span>
              ))}
            </div>

            {lanes.map((lane) => (
              <div key={lane.type} className="timeline-track">
                {periods
                  .filter((p) => p.type === lane.type)
                  .map((p) => {
                    const s = dayNumber(p.startDate);
                    const e = dayNumber(p.endDate);
                    if (e < start || s > end) return null;
                    const phase = phaseOf(p, today);
                    return (
                      <span
                        key={p.id}
                        className={`timeline-bar bar-${lane.type.toLowerCase()} bar-${phase}`}
                        style={{ left: `${pos(s)}%`, width: `${Math.max(pos(e + 1) - pos(s), 0.8)}%` }}
                        title={`${p.label}: ${formatDayOnly(p.startDate)} até ${formatDayOnly(p.endDate)}`}
                      >
                        <span>{p.label}</span>
                      </span>
                    );
                  })}
              </div>
            ))}

            <div className="timeline-track timeline-track-milestones">
              {visibleMilestones.map((m, i) => (
                <span
                  key={m.id}
                  className={`timeline-milestone ms-${(m.status ?? 'UPCOMING').toLowerCase()}${i % 2 ? ' ms-low' : ''}`}
                  style={{ left: `${pos(dayNumber(m.dueDate))}%` }}
                  title={`${m.name}: até ${formatDate(m.dueDate)}`}
                >
                  <i />
                  <span>{m.name}</span>
                </span>
              ))}
            </div>

            {today >= start && today <= end && (
              <span className="timeline-today" style={{ left: `${pos(today)}%` }}>
                <span>Hoje</span>
              </span>
            )}
          </div>
        </div>
      </div>
    </section>
  );
}
