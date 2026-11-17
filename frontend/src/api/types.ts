export type Role = 'STUDENT' | 'ADVISOR' | 'EVALUATOR' | 'COORDINATION';

export type TccStatus =
  | 'DRAFT'
  | 'SUBMITTED'
  | 'RESUBMITTED'
  | 'SENT_TO_EVALUATION'
  | 'CORRECTIONS_REQUESTED'
  | 'ADVISOR_FINAL_REVIEW'
  | 'AWAITING_DEFENSE'
  | 'AWAITING_DECISION'
  | 'APPROVED'
  | 'REJECTED';

export type EvaluationVerdict = 'PENDING' | 'APPROVED' | 'CORRECTIONS_REQUESTED';

export const ROLE_LABELS: Record<Role, string> = {
  STUDENT: 'Aluno',
  ADVISOR: 'Orientador',
  EVALUATOR: 'Avaliador',
  COORDINATION: 'Coordenação',
};

/** Ordem canônica dos papéis (a mesma da API). */
export const ROLES: Role[] = ['STUDENT', 'ADVISOR', 'EVALUATOR', 'COORDINATION'];

export interface UserSummary {
  id: string;
  name: string;
  email: string;
}

/** Usuário como a coordenação vê (e como /me e o login devolvem). */
export interface User extends UserSummary {
  roles: Role[];
  createdAt: string;
}

export interface AuthResponse {
  accessToken: string;
  user: User;
}

export interface CatalogItem {
  id: string;
  name: string;
  /** Quantos TCCs usam (só na listagem do catálogo). */
  tccCount?: number;
}

export type ThematicArea = CatalogItem;
export type Keyword = CatalogItem;

export interface BoardMember {
  id: string;
  evaluator: UserSummary;
  verdict: EvaluationVerdict;
  comment: string | null;
  /** O usuário logado é este avaliador. */
  isMe: boolean;
}

export interface TccStatusHistoryEntry {
  id: string;
  fromStatus: TccStatus | null;
  toStatus: TccStatus;
  transition: string;
  comment: string | null;
  changedBy: UserSummary | null;
  createdAt: string;
}

export interface TccDocument {
  version: number;
  originalName: string;
  sizeBytes: number;
  uploadedAt: string;
  /** Link assinado, válido por 5 minutos. */
  downloadUrl: string;
}

export interface TccPermissions {
  edit: boolean;
  upload: boolean;
  submit: boolean;
  advisorReview: boolean;
  evaluate: boolean;
  manageBoard: boolean;
}

export interface Tcc {
  id: string;
  title: string;
  abstract: string;
  status: TccStatus;
  student: UserSummary;
  advisor: UserSummary | null;
  thematicAreas: CatalogItem[];
  keywords: CatalogItem[];
  document: TccDocument | null;
  documents: TccDocument[];
  board: BoardMember[];
  statusHistory: TccStatusHistoryEntry[];
  finalGrade: number | null;
  publicationAuthorized: boolean;
  publishedAt: string | null;
  createdAt: string;
  updatedAt: string;
  /** Há prazo de avaliação aberto agora. */
  evaluationPeriodOpen: boolean;
  permissions: TccPermissions;
}

export interface SubmissionPeriod {
  id: string;
  type: 'SUBMISSION' | 'EVALUATION';
  label: string;
  startDate: string;
  endDate: string;
  active: boolean;
}

export interface Settings {
  boardMinSize: number;
  boardMaxSize: number;
  passingGrade: number;
}

export const STATUS_LABELS: Record<TccStatus, string> = {
  DRAFT: 'Rascunho',
  SUBMITTED: 'Submetido',
  RESUBMITTED: 'Reenviado',
  SENT_TO_EVALUATION: 'Em avaliação pela banca',
  CORRECTIONS_REQUESTED: 'Correções solicitadas',
  ADVISOR_FINAL_REVIEW: 'Revisão final do orientador',
  AWAITING_DEFENSE: 'Aguardando defesa',
  AWAITING_DECISION: 'Aguardando decisão da coordenação',
  APPROVED: 'Aprovado',
  REJECTED: 'Reprovado',
};

export const VERDICT_LABELS: Record<EvaluationVerdict, string> = {
  PENDING: 'Aguardando parecer',
  APPROVED: 'Aprovado',
  CORRECTIONS_REQUESTED: 'Correção solicitada',
};
