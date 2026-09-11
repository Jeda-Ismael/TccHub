import { EvaluationVerdict, VERDICT_LABELS } from '../api/types';

const CLASS_BY_VERDICT: Record<EvaluationVerdict, string> = {
  PENDING: 'status-SUBMITTED',
  APPROVED: 'status-APPROVED',
  CORRECTIONS_REQUESTED: 'status-CORRECTIONS_REQUESTED',
};

export function VerdictBadge({ verdict }: { verdict: EvaluationVerdict }) {
  return <span className={`status-badge ${CLASS_BY_VERDICT[verdict]}`}>{VERDICT_LABELS[verdict]}</span>;
}
