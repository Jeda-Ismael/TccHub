export type Role = 'STUDENT' | 'ADVISOR' | 'EVALUATOR' | 'COORDINATION';

export type TccStatus =
  | 'DRAFT'
  | 'SUBMITTED'
  | 'ADVISOR_REVIEW'
  | 'SENT_TO_EVALUATION'
  | 'CORRECTIONS_REQUESTED'
  | 'RESUBMITTED'
  | 'ADVISOR_FINAL_REVIEW'
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

export interface ThematicArea {
  id: string;
  name: string;
  _count?: { tccs: number };
}

export interface Keyword {
  id: string;
  name: string;
  _count?: { tccs: number };
}

export interface TccEvaluator {
  id: string;
  evaluatorId: string;
  evaluator: UserSummary;
  verdict: EvaluationVerdict;
  comment?: string | null;
}

export interface TccStatusHistoryEntry {
  id: string;
  fromStatus: TccStatus | null;
  toStatus: TccStatus;
  comment?: string | null;
  changedBy?: UserSummary | null;
  createdAt: string;
}

export interface Tcc {
  id: string;
  title: string;
  abstract: string;
  status: TccStatus;
  publishable: boolean;
  projectGrade?: number | null;
  presentationGrade?: number | null;
  documentUrl?: string | null;
  documentVersion: number;
  student: UserSummary;
  advisor?: UserSummary | null;
  thematicAreas: { thematicArea: ThematicArea }[];
  keywords: { keyword: Keyword }[];
  evaluators: TccEvaluator[];
  statusHistory: TccStatusHistoryEntry[];
  createdAt: string;
  updatedAt: string;
}

export interface SubmissionPeriod {
  id: string;
  type: 'SUBMISSION' | 'EVALUATION';
  label: string;
  startDate: string;
  endDate: string;
}

export const STATUS_LABELS: Record<TccStatus, string> = {
  DRAFT: 'Rascunho',
  SUBMITTED: 'Submetido',
  ADVISOR_REVIEW: 'Em revisão do orientador',
  SENT_TO_EVALUATION: 'Em avaliação pela banca',
  CORRECTIONS_REQUESTED: 'Correções solicitadas',
  RESUBMITTED: 'Reenviado',
  ADVISOR_FINAL_REVIEW: 'Revisão final do orientador',
  APPROVED: 'Aprovado',
  REJECTED: 'Reprovado',
};

export const VERDICT_LABELS: Record<EvaluationVerdict, string> = {
  PENDING: 'Aguardando parecer',
  APPROVED: 'Aprovado',
  CORRECTIONS_REQUESTED: 'Correção solicitada',
};
