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
  /** false = desativado pela coordenação (não entra mais, histórico mantido). */
  active?: boolean;
  createdAt: string;
}

/** Uma página de lista (o total vem no cabeçalho X-Total-Count da API). */
export interface Page<T> {
  items: T[];
  total: number;
  page: number;
  perPage: number;
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
  scheduleDefense: boolean;
  gradeDefense: boolean;
  decide: boolean;
  authorizePublication: boolean;
  publish: boolean;
  /** Orientador pode registrar reunião de orientação. */
  recordMeeting?: boolean;
}

export type DefenseOutcome = 'PENDING' | 'PASSED' | 'FAILED';

export interface DefenseGrade {
  evaluator: UserSummary;
  projectGrade: number;
  presentationGrade: number;
  comment: string | null;
  isMe: boolean;
}

/** Tentativa de defesa atual. As notas vêm às cegas: cada avaliador vê só a própria até fechar. */
export interface Defense {
  attempt: number;
  scheduledAt: string | null;
  location: string | null;
  meetingUrl: string | null;
  hasStarted: boolean;
  outcome: DefenseOutcome;
  projectAverage: number | null;
  presentationAverage: number | null;
  finalGrade: number | null;
  gradedCount: number;
  boardSize: number;
  grades: DefenseGrade[];
  /** Ata em PDF (link assinado, 5 minutos), só depois que a defesa encerra. */
  minutesUrl?: string | null;
}

export interface PreviousDefense {
  attempt: number;
  scheduledAt: string | null;
  finalGrade: number | null;
  outcome: DefenseOutcome;
  minutesUrl?: string | null;
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
  defense: Defense | null;
  previousDefenses: PreviousDefense[];
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

export type NotificationType =
  | 'TCC_SUBMITTED'
  | 'BOARD_ASSIGNED'
  | 'EVALUATION_OPEN'
  | 'CORRECTIONS_REQUESTED'
  | 'FINAL_REVIEW_PENDING'
  | 'DEFENSE_PENDING'
  | 'DEFENSE_SCHEDULED'
  | 'GRADES_PENDING'
  | 'DECISION_PENDING'
  | 'TCC_APPROVED'
  | 'TCC_REJECTED'
  | 'PUBLICATION_REQUESTED'
  | 'PUBLICATION_WITHDRAWN'
  | 'TCC_PUBLISHED'
  | 'TCC_UNPUBLISHED'
  | 'MEETING_RECORDED'
  | 'MEETING_CONFIRMED';

export interface AppNotification {
  id: string;
  type: NotificationType;
  title: string;
  body: string;
  tccId: string | null;
  /** Com que papel abrir o TCC. */
  as: Role | null;
  read: boolean;
  createdAt: string;
}

export interface NotificationList {
  items: AppNotification[];
  unreadCount: number;
}

/** Reunião de orientação: o orientador registra, o aluno confirma. */
export interface AdvisingMeeting {
  id: string;
  /** AAAA-MM-DD */
  heldOn: string;
  summary: string;
  nextSteps: string | null;
  advisor: UserSummary;
  confirmedAt: string | null;
  createdAt: string;
  permissions: { confirm: boolean; delete: boolean };
}

export type MilestoneStatus = 'DONE' | 'NEXT' | 'UPCOMING';

/** Etapa do cronograma do TCC, definida pela coordenação. */
export interface Milestone {
  id: string;
  name: string;
  description: string | null;
  /** AAAA-MM-DD */
  dueDate: string;
  /** Só na listagem. */
  status?: MilestoneStatus;
}

export interface MeetingInput {
  heldOn: string;
  summary: string;
  nextSteps?: string;
}

export interface MilestoneInput {
  name: string;
  description?: string;
  dueDate: string;
}

// --- Repositório ---
export interface RepositoryTcc {
  id: string;
  title: string;
  abstract: string;
  student: { id: string; name: string };
  advisor: { id: string; name: string } | null;
  thematicAreas: { id: string; name: string }[];
  keywords: { id: string; name: string }[];
  finalGrade: number | null;
  publishedAt: string;
}

export interface RepositoryTccDetail extends RepositoryTcc {
  document: { version: number; originalName: string; sizeBytes: number; downloadUrl: string } | null;
}

export interface RepositoryPage {
  items: RepositoryTcc[];
  total: number;
  page: number;
  pageSize: number;
  years: number[];
}

export interface RepositoryFilters {
  q?: string;
  area?: string;
  keyword?: string;
  year?: string;
  page?: number;
}
