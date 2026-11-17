import { useState } from 'react';
import { STATUS_LABELS, TccStatusHistoryEntry } from '../api/types';
import { formatDateTime } from '../utils/dates';

/** Quantos passos aparecem antes de "Ver histórico completo". */
const PREVIEW = 4;

export function StatusHistoryTimeline({ entries }: { entries: TccStatusHistoryEntry[] }) {
  const [showAll, setShowAll] = useState(false);
  if (!entries.length) return null;

  const ordered = [...entries].reverse();
  const shown = showAll ? ordered : ordered.slice(0, PREVIEW);

  return (
    <div className="card">
      <h3>Histórico</h3>
      <ol className="timeline">
        {shown.map((entry) => (
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
      {ordered.length > PREVIEW && (
        <button type="button" className="link-button" onClick={() => setShowAll((v) => !v)} aria-expanded={showAll}>
          {showAll ? 'Mostrar só os últimos passos' : `Ver histórico completo (${ordered.length})`}
        </button>
      )}
    </div>
  );
}
