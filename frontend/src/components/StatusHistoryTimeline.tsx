import { STATUS_LABELS, TccStatusHistoryEntry } from '../api/types';

export function StatusHistoryTimeline({ entries }: { entries: TccStatusHistoryEntry[] }) {
  if (!entries.length) return null;

  return (
    <div className="card">
      <h3>Histórico</h3>
      <ol className="timeline">
        {entries.map((entry) => (
          <li key={entry.id}>
            <div className="timeline-title">
              {entry.fromStatus ? `${STATUS_LABELS[entry.fromStatus]} → ` : ''}
              <strong>{STATUS_LABELS[entry.toStatus]}</strong>
            </div>
            <div className="timeline-meta">
              {new Date(entry.createdAt).toLocaleString('pt-BR')}
              {entry.changedBy ? ` · ${entry.changedBy.name}` : ''}
            </div>
            {entry.comment && <div className="timeline-comment">"{entry.comment}"</div>}
          </li>
        ))}
      </ol>
    </div>
  );
}
