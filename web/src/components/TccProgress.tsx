import { TccStatus } from '../api/types';
import { AlertIcon, CheckIcon } from './icons';

// Visualização do fluxo do TCC em etapas. Puramente derivada do status —
// não altera nenhuma regra de negócio, só mostra "onde o trabalho está".
const STEPS = ['Cadastro', 'Orientador', 'Banca', 'Revisão final', 'Defesa', 'Conclusão'];

function stepIndex(status: TccStatus): number {
  switch (status) {
    case 'DRAFT':
      return 0;
    case 'SUBMITTED':
    case 'RESUBMITTED':
      return 1;
    case 'SENT_TO_EVALUATION':
    case 'CORRECTIONS_REQUESTED':
      return 2;
    case 'ADVISOR_FINAL_REVIEW':
      return 3;
    case 'AWAITING_DEFENSE':
    case 'AWAITING_DECISION':
      return 4;
    case 'APPROVED':
    case 'REJECTED':
      return 5;
  }
}

const NOTES: Record<TccStatus, string> = {
  DRAFT: 'Em edição',
  SUBMITTED: 'Aguardando revisão',
  RESUBMITTED: 'Reenviado',
  SENT_TO_EVALUATION: 'Em avaliação',
  CORRECTIONS_REQUESTED: 'Correções pedidas',
  ADVISOR_FINAL_REVIEW: 'Com o orientador',
  AWAITING_DEFENSE: 'Aguardando defesa',
  AWAITING_DECISION: 'Decisão da coordenação',
  APPROVED: 'Aprovado',
  REJECTED: 'Reprovado',
};

export function TccProgress({ status }: { status: TccStatus }) {
  const current = stepIndex(status);
  const attention = status === 'CORRECTIONS_REQUESTED' || status === 'REJECTED';
  const finished = status === 'APPROVED';

  return (
    <ol className="progress" aria-label="Andamento do TCC">
      {STEPS.map((label, i) => {
        const done = i < current || (finished && i === current);
        const isCurrent = i === current && !finished;
        const cls = done ? 'done' : isCurrent ? (attention ? 'current attention' : 'current') : '';
        return (
          <li key={label} className={`progress-step ${cls}`} aria-current={isCurrent ? 'step' : undefined}>
            <span className="progress-dot" aria-hidden="true">
              {done ? <CheckIcon size={15} /> : isCurrent && attention ? <AlertIcon size={15} /> : i + 1}
            </span>
            <span className="progress-label">
              {label}
              {i === current && <span className="progress-note">{NOTES[status]}</span>}
            </span>
          </li>
        );
      })}
    </ol>
  );
}
