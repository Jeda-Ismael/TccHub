import { useMilestones } from '../api/hooks';
import { MilestoneStatus } from '../api/types';
import { formatDate } from '../utils/dates';

const STATUS_LABELS: Record<MilestoneStatus, string> = {
  DONE: 'Prazo passou',
  NEXT: 'Próxima entrega',
  UPCOMING: 'Depois',
};

/** Cronograma do semestre (definido pela coordenação), com a próxima entrega em destaque. */
export function MilestonesCard() {
  const { data } = useMilestones();
  const milestones = Array.isArray(data) ? data : [];
  if (!milestones.length) return null;

  return (
    <div className="card">
      <h3>Cronograma</h3>
      <ol className="milestone-list">
        {milestones.map((m) => (
          <li key={m.id} className={`milestone milestone-${(m.status ?? 'UPCOMING').toLowerCase()}`}>
            <div className="milestone-head">
              <strong>{m.name}</strong>
              <span className="milestone-date">{formatDate(m.dueDate)}</span>
            </div>
            {m.status && m.status !== 'UPCOMING' && <span className="milestone-tag">{STATUS_LABELS[m.status]}</span>}
            {m.description && <p className="muted">{m.description}</p>}
          </li>
        ))}
      </ol>
    </div>
  );
}
