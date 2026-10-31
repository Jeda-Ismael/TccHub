import { STATUS_LABELS, TccStatusHistoryEntry } from '../api/types';

export function StatusHistoryTimeline({ entries }: { entries: TccStatusHistoryEntry[] }) {
  if (!entries.length) return null;

  return (
    <div className="card">
      <h3>Histórico</h3>
      <div style={{ display: 'flex', flexDirection: 'column', gap: '0.6rem' }}>
        {[...entries].reverse().map((entry) => (
          <div key={entry.id} style={{ fontSize: '0.85rem', borderLeft: '2px solid var(--border)', paddingLeft: '0.75rem' }}>
            <div>
              {entry.fromStatus ? `${STATUS_LABELS[entry.fromStatus]} → ` : ''}
              <strong>{STATUS_LABELS[entry.toStatus]}</strong>
            </div>
            <div className="muted">
              {new Date(entry.createdAt).toLocaleString('pt-BR')}
              {entry.changedBy ? ` · ${entry.changedBy.name}` : ''}
            </div>
            {entry.comment && <div className="muted">"{entry.comment}"</div>}
          </div>
        ))}
      </div>
    </div>
  );
}
