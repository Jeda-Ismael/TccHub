import { TccStatus } from '../api/types';
import { AlertIcon, CheckIcon } from './icons';

// Visualização do fluxo do TCC em 5 etapas. Puramente derivada do status —
// não altera nenhuma regra de negócio, só mostra "onde o trabalho está".
const STEPS = ['Cadastro', 'Orientador', 'Banca', 'Revisão final', 'Conclusão'];

function stepIndex(status: TccStatus): number {
  switch (status) {
    case 'DRAFT':
      return 0;
    case 'SUBMITTED':
    case 'RESUBMITTED':
    case 'ADVISOR_REVIEW':
      return 1;
    case 'SENT_TO_EVALUATION':
    case 'CORRECTIONS_REQUESTED':
      return 2;
    case 'ADVISOR_FINAL_REVIEW':
      return 3;
    case 'APPROVED':
    case 'REJECTED':
      return 4;
  }
}

function currentNote(status: TccStatus): string {
  switch (status) {
    case 'DRAFT':
      return 'Em edição';
    case 'SUBMITTED':
      return 'Aguardando revisão';
    case 'RESUBMITTED':
      return 'Reenviado';
    case 'ADVISOR_REVIEW':
      return 'Em revisão';
    case 'SENT_TO_EVALUATION':
      return 'Em avaliação';
    case 'CORRECTIONS_REQUESTED':
      return 'Correções pedidas';
    case 'ADVISOR_FINAL_REVIEW':
      return 'Aguardando orientador';
    case 'APPROVED':
      return 'Aprovado';
    case 'REJECTED':
      return 'Reprovado';
  }
}

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
            <span className="progress-dot">
              {done ? <CheckIcon size={15} /> : isCurrent && attention ? <AlertIcon size={15} /> : i + 1}
            </span>
            <span className="progress-label">
              {label}
              {(isCurrent || (finished && i === current)) && <span className="progress-note">{currentNote(status)}</span>}
            </span>
          </li>
        );
      })}
    </ol>
  );
}
