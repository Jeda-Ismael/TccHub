import { STATUS_LABELS, TccStatusHistoryEntry } from '../api/types';
import { formatDateTime } from '../utils/dates';

export function StatusHistoryTimeline({ entries }: { entries: TccStatusHistoryEntry[] }) {
  if (!entries.length) return null;

  return (
    <div className="card">
      <h3>Histórico</h3>
      <ol className="timeline">
        {[...entries].reverse().map((entry) => (
          <li key={entry.id}>
            <div className="timeline-title">
              {entry.fromStatus ? `${STATUS_LABELS[entry.fromStatus]} → ` : ''}
              <strong>{STATUS_LABELS[entry.toStatus]}</strong>
            </div>
            <div className="timeline-meta">
              {formatDateTime(entry.createdAt)}
              {entry.changedBy ? ` · ${entry.changedBy.name}` : ''}
            </div>
            {entry.comment && <div className="timeline-comment">"{entry.comment}"</div>}
          </li>
        ))}
      </ol>
    </div>
  );
}
