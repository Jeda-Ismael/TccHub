import { FormEvent, useState } from 'react';
import { apiErrorMessage } from '../../api/client';
import { useCreateMilestone, useDeleteMilestone, useMilestones, useUpdateMilestone } from '../../api/hooks';
import { Milestone } from '../../api/types';
import { Drawer } from '../../components/Drawer';
import { EmptyState } from '../../components/EmptyState';
import { PlusIcon } from '../../components/icons';
import { toast } from '../../components/Toaster';
import { formatDate } from '../../utils/dates';
import { dayNumber, relativeDays, todayNumber } from './schedule';

/** Cronograma do TCC (projeto, qualificação, versão final...), que alunos e orientadores veem. */
export function Milestones() {
  const { data } = useMilestones();
  const milestones = Array.isArray(data) ? data : [];
  const remove = useDeleteMilestone();
  const today = todayNumber();

  const [form, setForm] = useState<{ milestone: Milestone | null } | null>(null);
  const [confirmingDeleteId, setConfirmingDeleteId] = useState<string | null>(null);

  async function handleDelete(m: Milestone) {
    await remove.mutateAsync(m.id);
    setConfirmingDeleteId(null);
    toast('Etapa excluída.');
  }

  return (
    <section className="card">
      <div className="section-head">
        <h2>Cronograma do TCC</h2>
        <button type="button" className="secondary small" onClick={() => setForm({ milestone: null })}>
          <PlusIcon size={16} />
          Nova etapa
        </button>
      </div>
      <p className="card-sub">Alunos e orientadores veem estas entregas, com a próxima em destaque.</p>

      {!milestones.length && <EmptyState title="Nenhuma etapa cadastrada.">Cadastre as entregas do semestre, como projeto, qualificação e versão final.</EmptyState>}

      <ol className="steps">
        {milestones.map((m) => {
          const status = (m.status ?? 'UPCOMING').toLowerCase();
          const diff = dayNumber(m.dueDate) - today;
          return (
            <li key={m.id} className={`step step-${status}`}>
              <span className="step-dot" aria-hidden="true" />
              <div className="step-body">
                <div className="step-top">
                  <h3>{m.name}</h3>
                  {m.status === 'NEXT' && <span className="next-tag">Próxima</span>}
                </div>
                <p className="step-date">
                  Até {formatDate(m.dueDate)} <span>({relativeDays(diff)})</span>
                </p>
                {m.description && <p className="step-desc">{m.description}</p>}
              </div>
              {confirmingDeleteId === m.id ? (
                <div className="row-actions confirming">
                  <span>Excluir?</span>
                  <button type="button" className="danger solid small" onClick={() => handleDelete(m)}>
                    Confirmar
                  </button>
                  <button type="button" className="secondary small" onClick={() => setConfirmingDeleteId(null)}>
                    Cancelar
                  </button>
                </div>
              ) : (
                <div className="row-actions">
                  <button type="button" className="secondary small" onClick={() => setForm({ milestone: m })} aria-label={`Editar etapa ${m.name}`}>
                    Editar
                  </button>
                  <button type="button" className="ghost-danger small" onClick={() => setConfirmingDeleteId(m.id)} aria-label={`Excluir etapa ${m.name}`}>
                    Excluir
                  </button>
                </div>
              )}
            </li>
          );
        })}
      </ol>

      <Drawer open={form !== null} title={form?.milestone ? 'Editar etapa' : 'Nova etapa'} description="A etapa aparece no painel de todos os alunos e orientadores." onClose={() => setForm(null)}>
        {form && <MilestoneForm key={form.milestone?.id ?? 'new'} milestone={form.milestone} onDone={() => setForm(null)} />}
      </Drawer>
    </section>
  );
}

function MilestoneForm({ milestone, onDone }: { milestone: Milestone | null; onDone: () => void }) {
  const create = useCreateMilestone();
  const update = useUpdateMilestone();
  const [name, setName] = useState(milestone?.name ?? '');
  const [description, setDescription] = useState(milestone?.description ?? '');
  const [dueDate, setDueDate] = useState(milestone?.dueDate ?? '');
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    const input = { name, description: description.trim() || undefined, dueDate };
    try {
      if (milestone) {
        await update.mutateAsync({ id: milestone.id, ...input });
      } else {
        await create.mutateAsync(input);
      }
      toast(milestone ? 'Etapa atualizada.' : 'Etapa criada.');
      onDone();
    } catch (err) {
      setError(apiErrorMessage(err, 'Não foi possível salvar a etapa.'));
    }
  }

  return (
    <form onSubmit={handleSubmit} className="drawer-form">
      <div className="form-group">
        <label htmlFor="milestone-name">Nome (ex: Qualificação)</label>
        <input id="milestone-name" value={name} onChange={(e) => setName(e.target.value)} required maxLength={120} />
      </div>
      <div className="form-group">
        <label htmlFor="milestone-description">O que entregar (opcional)</label>
        <textarea id="milestone-description" rows={3} value={description} onChange={(e) => setDescription(e.target.value)} maxLength={1000} />
      </div>
      <div className="form-group">
        <label htmlFor="milestone-date">Data limite</label>
        <input id="milestone-date" type="date" value={dueDate} onChange={(e) => setDueDate(e.target.value)} required aria-describedby="milestone-date-preview" />
        <p id="milestone-date-preview" className="field-hint">
          {dueDate ? formatDate(dueDate) : 'dd/mm/aaaa'}
        </p>
      </div>
      {error && (
        <p className="error-text" role="alert">
          {error}
        </p>
      )}
      <div className="drawer-actions">
        <button type="button" className="secondary" onClick={onDone}>
          Cancelar
        </button>
        <button type="submit" disabled={create.isPending || update.isPending}>
          {milestone ? 'Salvar etapa' : 'Criar etapa'}
        </button>
      </div>
    </form>
  );
}
