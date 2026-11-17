import { STATUS_LABELS, TccStatus } from '../api/types';

export function StatusBadge({ status }: { status: TccStatus }) {
  return <span className={`status-badge status-${status}`}>{STATUS_LABELS[status]}</span>;
}
