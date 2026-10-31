import { FormEvent, useState } from 'react';
import { apiErrorMessage } from '../api/client';
import { useDecide, useGradeDefense, useScheduleDefense } from '../api/hooks';
import { Defense, DefenseOutcome, Tcc } from '../api/types';
import { formatDate, formatLongDateTime } from '../utils/dates';

const OUTCOME_LABELS: Record<DefenseOutcome, string> = {
  PENDING: 'Em andamento',
  PASSED: 'Aprovado',
  FAILED: 'Abaixo da nota mínima',
};

const OUTCOME_CLASS: Record<DefenseOutcome, string> = {
  PENDING: 'status-AWAITING_DEFENSE',
  PASSED: 'status-APPROVED',
  FAILED: 'status-REJECTED',
};

/** Nota com vírgula e duas casas: 8.5 → "8,50". */
export function formatGrade(grade: number | null): string {
  return grade === null ? '—' : grade.toFixed(2).replace('.', ',');
}

/**
 * Converte o ISO da API para o valor do campo datetime-local (AAAA-MM-DDTHH:mm),
 * mantendo o horário como a API mandou (já no fuso da instituição).
 */
function toInputValue(iso: string | null) {
  return iso ? iso.slice(0, 16) : '';
}

/** Tudo sobre a defesa do TCC: informações, notas e as ações permitidas a quem está vendo. */
export function DefenseSection({ tcc }: { tcc: Tcc }) {
  const { defense, previousDefenses, permissions } = tcc;
  if (!defense && previousDefenses.length === 0) return null;

  return (
    <div className="card">
      <div className="tcc-header">
        <h3>Defesa{defense && defense.attempt > 1 ? ` (tentativa ${defense.attempt})` : ''}</h3>
        {defense && <span className={`status-badge ${OUTCOME_CLASS[defense.outcome]}`}>{OUTCOME_LABELS[defense.outcome]}</span>}
      </div>

      {defense && <DefenseInfo defense={defense} />}
      {defense && <DefenseGrades defense={defense} />}

      {permissions.scheduleDefense && defense && <ScheduleForm tcc={tcc} defense={defense} />}
      {permissions.gradeDefense && defense && <GradeForm tcc={tcc} defense={defense} />}
      {permissions.decide && <DecisionForm tcc={tcc} />}

      {previousDefenses.length > 0 && (
        <details style={{ marginTop: '1rem' }}>
          <summary className="muted">Tentativas anteriores</summary>
          <ul>
            {previousDefenses.map((d) => (
              <li key={d.attempt}>
                Tentativa {d.attempt}: média {formatGrade(d.finalGrade)} · {OUTCOME_LABELS[d.outcome]}
                {d.scheduledAt && <span className="muted"> · {formatDate(d.scheduledAt)}</span>}
              </li>
            ))}
          </ul>
        </details>
      )}
    </div>
  );
}

function DefenseInfo({ defense }: { defense: Defense }) {
  if (!defense.scheduledAt) {
    return <p className="muted">Aguardando o agendamento pelo orientador ou pela coordenação.</p>;
  }

  return (
    <div className="defense-info">
      <p>
        <strong>{formatLongDateTime(defense.scheduledAt)}</strong>
      </p>
      {defense.location && <p>Local: {defense.location}</p>}
      {defense.meetingUrl && (
        <p>
          Link:{' '}
          <a href={defense.meetingUrl} target="_blank" rel="noreferrer">
            {defense.meetingUrl}
          </a>
        </p>
      )}
      {defense.outcome === 'PENDING' && defense.hasStarted && (
        <p className="muted">
          Notas lançadas: {defense.gradedCount} de {defense.boardSize}. O resultado sai quando toda a banca lançar.
        </p>
      )}
    </div>
  );
}

function DefenseGrades({ defense }: { defense: Defense }) {
  const closed = defense.outcome !== 'PENDING';
  if (!closed && defense.grades.length === 0) return null;

  return (
    <div style={{ marginTop: '0.75rem' }}>
      {closed && (
        <p className="defense-result">
          Projeto {formatGrade(defense.projectAverage)} · Apresentação {formatGrade(defense.presentationAverage)} ·{' '}
          <strong>Média final {formatGrade(defense.finalGrade)}</strong>
        </p>
      )}
      {defense.grades.length > 0 && (
        <table className="grades-table">
          <thead>
            <tr>
              <th>Avaliador</th>
              <th>Projeto</th>
              <th>Apresentação</th>
              <th>Comentário</th>
            </tr>
          </thead>
          <tbody>
            {defense.grades.map((g) => (
              <tr key={g.evaluator.id}>
                <td>
                  {g.evaluator.name}
                  {g.isMe && <span className="muted"> (você)</span>}
                </td>
                <td>{formatGrade(g.projectGrade)}</td>
                <td>{formatGrade(g.presentationGrade)}</td>
                <td className="muted">{g.comment ?? ''}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}

function ScheduleForm({ tcc, defense }: { tcc: Tcc; defense: Defense }) {
  const schedule = useScheduleDefense(tcc.id);
  const [scheduledAt, setScheduledAt] = useState(toInputValue(defense.scheduledAt));
  const [location, setLocation] = useState(defense.location ?? '');
  const [meetingUrl, setMeetingUrl] = useState(defense.meetingUrl ?? '');
  const [message, setMessage] = useState<{ text: string; error: boolean } | null>(null);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setMessage(null);
    try {
      await schedule.mutateAsync({ scheduledAt, location: location.trim() || undefined, meetingUrl: meetingUrl.trim() || undefined });
      setMessage({ text: 'Defesa agendada.', error: false });
    } catch (err) {
      setMessage({ text: apiErrorMessage(err, 'Não foi possível agendar.'), error: true });
    }
  }

  return (
    <form onSubmit={handleSubmit} className="defense-form">
      <h4>{defense.scheduledAt ? 'Reagendar' : 'Agendar a defesa'}</h4>
      <div className="form-group">
        <label htmlFor="defense-at">Data e hora</label>
        <input id="defense-at" type="datetime-local" value={scheduledAt} onChange={(e) => setScheduledAt(e.target.value)} required />
      </div>
      <div className="form-group">
        <label htmlFor="defense-location">Sala (ou deixe vazio se for on-line)</label>
        <input id="defense-location" value={location} onChange={(e) => setLocation(e.target.value)} maxLength={255} />
      </div>
      <div className="form-group">
        <label htmlFor="defense-link">Link da reunião (opcional)</label>
        <input id="defense-link" type="url" value={meetingUrl} onChange={(e) => setMeetingUrl(e.target.value)} placeholder="https://..." />
      </div>
      {message && (
        <p className={message.error ? 'error-text' : 'muted'} role={message.error ? 'alert' : undefined}>
          {message.text}
        </p>
      )}
      <button type="submit" disabled={schedule.isPending}>
        {defense.scheduledAt ? 'Salvar novo horário' : 'Agendar'}
      </button>
    </form>
  );
}

function GradeForm({ tcc, defense }: { tcc: Tcc; defense: Defense }) {
  const gradeDefense = useGradeDefense(tcc.id);
  const mine = defense.grades.find((g) => g.isMe);
  const [projectGrade, setProjectGrade] = useState(mine ? String(mine.projectGrade) : '');
  const [presentationGrade, setPresentationGrade] = useState(mine ? String(mine.presentationGrade) : '');
  const [comment, setComment] = useState(mine?.comment ?? '');
  const [message, setMessage] = useState<{ text: string; error: boolean } | null>(null);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setMessage(null);
    try {
      const updated = await gradeDefense.mutateAsync({
        projectGrade: Number(projectGrade.replace(',', '.')),
        presentationGrade: Number(presentationGrade.replace(',', '.')),
        comment: comment.trim() || undefined,
      });
      setMessage({ text: updated.defense?.outcome === 'PENDING' ? 'Notas registradas.' : 'Notas registradas. A defesa foi encerrada.', error: false });
    } catch (err) {
      setMessage({ text: apiErrorMessage(err, 'Não foi possível registrar as notas.'), error: true });
    }
  }

  return (
    <form onSubmit={handleSubmit} className="defense-form">
      <h4>{mine ? 'Corrigir suas notas' : 'Suas notas'}</h4>
      <p className="muted">De 0 a 10. Você pode corrigir até toda a banca lançar; as notas dos colegas aparecem quando a defesa encerrar.</p>
      <div className="grade-inputs">
        <div className="form-group">
          <label htmlFor="grade-project">Projeto</label>
          <input id="grade-project" inputMode="decimal" value={projectGrade} onChange={(e) => setProjectGrade(e.target.value)} required />
        </div>
        <div className="form-group">
          <label htmlFor="grade-presentation">Apresentação</label>
          <input id="grade-presentation" inputMode="decimal" value={presentationGrade} onChange={(e) => setPresentationGrade(e.target.value)} required />
        </div>
      </div>
      <div className="form-group">
        <label htmlFor="grade-comment">Comentário (opcional)</label>
        <textarea id="grade-comment" value={comment} onChange={(e) => setComment(e.target.value)} />
      </div>
      {message && (
        <p className={message.error ? 'error-text' : 'muted'} role={message.error ? 'alert' : undefined}>
          {message.text}
        </p>
      )}
      <button type="submit" disabled={gradeDefense.isPending}>
        {mine ? 'Salvar correção' : 'Lançar notas'}
      </button>
    </form>
  );
}

function DecisionForm({ tcc }: { tcc: Tcc }) {
  const decide = useDecide(tcc.id);
  const [comment, setComment] = useState('');
  const [error, setError] = useState<string | null>(null);

  async function handle(action: 'new_defense' | 'reject') {
    setError(null);
    try {
      await decide.mutateAsync({ action, comment });
    } catch (err) {
      setError(apiErrorMessage(err, 'Não foi possível registrar a decisão.'));
    }
  }

  return (
    <div className="defense-form">
      <h4>Decisão da coordenação</h4>
      <p className="muted">A média ficou abaixo da nota mínima. Permita uma nova defesa ou reprove o TCC.</p>
      <div className="form-group">
        <label htmlFor="decision-comment">Justificativa (obrigatória)</label>
        <textarea id="decision-comment" value={comment} onChange={(e) => setComment(e.target.value)} />
      </div>
      {error && (
        <p className="error-text" role="alert">
          {error}
        </p>
      )}
      <div className="btn-row">
        <button type="button" onClick={() => handle('new_defense')} disabled={decide.isPending}>
          Permitir nova defesa
        </button>
        <button type="button" className="danger" onClick={() => handle('reject')} disabled={decide.isPending}>
          Reprovar
        </button>
      </div>
    </div>
  );
}
