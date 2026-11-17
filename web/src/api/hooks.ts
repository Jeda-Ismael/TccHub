import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from './client';
import { Keyword, NotificationList, Page, RepositoryFilters, RepositoryPage, RepositoryTccDetail, Role, Settings, SubmissionPeriod, Tcc, TccStatus, ThematicArea, User, UserSummary } from './types';

// --- TCC ---
/** TCCs visíveis com um papel: os próprios, os orientados, os das bancas ou todos. */
export function useTccs(as: Role) {
  return useQuery({
    queryKey: ['tccs', as],
    queryFn: async () => (await api.get<Tcc[]>('/tcc', { params: { as } })).data,
  });
}

export const PAGE_SIZE = 20;

/** Monta a página a partir do corpo (array) e do cabeçalho X-Total-Count. */
function toPage<T>(data: T[], headers: Record<string, unknown>, page: number, perPage: number): Page<T> {
  const total = Number(headers['x-total-count'] ?? data.length);
  return { items: data, total: Number.isFinite(total) ? total : data.length, page, perPage };
}

/** Lista da coordenação com busca, filtro de status e paginação feitos na API. */
export function useTccPage(as: Role, filters: { q: string; status: TccStatus | ''; page: number }) {
  return useQuery({
    queryKey: ['tccs', as, 'page', filters],
    queryFn: async () => {
      const params = { as, page: filters.page, perPage: PAGE_SIZE, q: filters.q || undefined, status: filters.status || undefined };
      const response = await api.get<Tcc[]>('/tcc', { params });
      return toPage(response.data, response.headers as Record<string, unknown>, filters.page, PAGE_SIZE);
    },
    placeholderData: (previous) => previous,
  });
}

export function useTcc(id?: string) {
  return useQuery({
    queryKey: ['tcc', id],
    queryFn: async () => (await api.get<Tcc>(`/tcc/${id}`)).data,
    enabled: !!id,
  });
}

/** Atualiza o TCC no cache com a resposta da API e recarrega as listas. */
function useTccMutation<TInput>(fn: (input: TInput) => Promise<Tcc>) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: fn,
    onSuccess: (tcc) => {
      qc.setQueryData(['tcc', tcc.id], tcc);
      qc.invalidateQueries({ queryKey: ['tccs'] });
      qc.invalidateQueries({ queryKey: ['repository'] });
    },
  });
}

export interface TccInput {
  title?: string;
  abstract?: string;
  /** "" remove o orientador. */
  advisorId?: string;
  thematicAreaIds?: string[];
  keywordIds?: string[];
}

export function useCreateTcc() {
  return useTccMutation(async (data: TccInput) => (await api.post<Tcc>('/tcc', data)).data);
}

export function useUpdateTcc(id: string) {
  return useTccMutation(async (data: TccInput) => (await api.patch<Tcc>(`/tcc/${id}`, data)).data);
}

export function useUploadDocument(id: string) {
  return useTccMutation(async (file: File) => {
    const form = new FormData();
    form.append('file', file);
    return (await api.post<Tcc>(`/tcc/${id}/document`, form)).data;
  });
}

export function useSubmitTcc(id: string) {
  return useTccMutation(async () => (await api.post<Tcc>(`/tcc/${id}/submit`)).data);
}

export function useAssignBoard(id: string) {
  return useTccMutation(async (evaluatorIds: string[]) => (await api.put<Tcc>(`/tcc/${id}/evaluators`, { evaluatorIds })).data);
}

export function useAdvisorReview(id: string) {
  return useTccMutation(async (data: { approve: boolean; comment?: string }) => (await api.post<Tcc>(`/tcc/${id}/advisor-review`, data)).data);
}

export function useEvaluate(id: string) {
  return useTccMutation(
    async (data: { verdict: 'APPROVED' | 'CORRECTIONS_REQUESTED'; comment?: string }) => (await api.post<Tcc>(`/tcc/${id}/evaluate`, data)).data,
  );
}

export function useScheduleDefense(id: string) {
  return useTccMutation(
    async (data: { scheduledAt: string; location?: string; meetingUrl?: string }) => (await api.put<Tcc>(`/tcc/${id}/defense`, data)).data,
  );
}

export function useGradeDefense(id: string) {
  return useTccMutation(
    async (data: { projectGrade: number; presentationGrade: number; comment?: string }) => (await api.put<Tcc>(`/tcc/${id}/defense/grade`, data)).data,
  );
}

export function useDecide(id: string) {
  return useTccMutation(
    async (data: { action: 'new_defense' | 'reject'; comment: string }) => (await api.post<Tcc>(`/tcc/${id}/decision`, data)).data,
  );
}

export function useAuthorizePublication(id: string) {
  return useTccMutation(async (authorized: boolean) => (await api.put<Tcc>(`/tcc/${id}/publication-authorization`, { authorized })).data);
}

/** true publica; false tira do repositório. */
export function usePublish(id: string) {
  return useTccMutation(async (publish: boolean) => (publish ? await api.post<Tcc>(`/tcc/${id}/publish`) : await api.delete<Tcc>(`/tcc/${id}/publish`)).data);
}

// --- Repositório ---
export function useRepositorySearch(filters: RepositoryFilters) {
  return useQuery({
    queryKey: ['repository', 'search', filters],
    queryFn: async () => (await api.get<RepositoryPage>('/repository', { params: filters })).data,
    placeholderData: (previous) => previous,
  });
}

export function useRepositoryTcc(id?: string) {
  return useQuery({
    queryKey: ['repository', 'tcc', id],
    queryFn: async () => (await api.get<RepositoryTccDetail>(`/repository/${id}`)).data,
    enabled: !!id,
  });
}

// --- Configurações ---
export function useSettings() {
  return useQuery({ queryKey: ['settings'], queryFn: async () => (await api.get<Settings>('/settings')).data });
}

export function useUpdateSettings() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (data: Settings) => (await api.put<Settings>('/settings', data)).data,
    onSuccess: (settings) => qc.setQueryData(['settings'], settings),
  });
}

// --- Catálogos ---
export function useThematicAreas() {
  return useQuery({ queryKey: ['thematic-areas'], queryFn: async () => (await api.get<ThematicArea[]>('/thematic-areas')).data });
}

export function useCreateThematicArea() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (name: string) => (await api.post<ThematicArea>('/thematic-areas', { name })).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['thematic-areas'] }),
  });
}

export function useUpdateThematicArea() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, name }: { id: string; name: string }) => (await api.put<ThematicArea>(`/thematic-areas/${id}`, { name })).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['thematic-areas'] }),
  });
}

export function useDeleteThematicArea() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id: string) => (await api.delete(`/thematic-areas/${id}`)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['thematic-areas'] }),
  });
}

export function useKeywords() {
  return useQuery({ queryKey: ['keywords'], queryFn: async () => (await api.get<Keyword[]>('/keywords')).data });
}

export function useCreateKeyword() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (name: string) => (await api.post<Keyword>('/keywords', { name })).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['keywords'] }),
  });
}

export function useUpdateKeyword() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, name }: { id: string; name: string }) => (await api.put<Keyword>(`/keywords/${id}`, { name })).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['keywords'] }),
  });
}

export function useDeleteKeyword() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id: string) => (await api.delete(`/keywords/${id}`)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['keywords'] }),
  });
}

// --- Usuários ---
/** Usuários com um papel (ex.: orientadores) — visão resumida, para qualquer logado. */
export function useUsers(role: Role) {
  return useQuery({
    queryKey: ['users', role],
    queryFn: async () => (await api.get<UserSummary[]>('/users', { params: { role } })).data,
  });
}

/** Todos os usuários com papéis — só a coordenação pode. */
export function useAllUsers() {
  return useQuery({
    queryKey: ['users', 'all'],
    queryFn: async () => (await api.get<User[]>('/users')).data,
  });
}

/** Gestão de usuários: busca, filtro de papel e paginação feitos na API (inclui desativados). */
export function useUserPage(filters: { q: string; role: Role | ''; page: number }) {
  return useQuery({
    queryKey: ['users', 'page', filters],
    queryFn: async () => {
      const params = {
        page: filters.page,
        perPage: PAGE_SIZE,
        q: filters.q || undefined,
        role: filters.role || undefined,
        includeInactive: filters.role ? true : undefined,
      };
      const response = await api.get<User[]>('/users', { params });
      return toPage(response.data, response.headers as Record<string, unknown>, filters.page, PAGE_SIZE);
    },
    placeholderData: (previous) => previous,
  });
}

export function useSetUserActive() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, active }: { id: string; active: boolean }) => (await api.put<User>(`/users/${id}/active`, { active })).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['users'] }),
  });
}

export function useUpdateUserRoles() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, roles }: { id: string; roles: Role[] }) => (await api.put<User>(`/users/${id}/roles`, { roles })).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['users'] }),
  });
}

export function useCreateUser() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (data: { name: string; email: string; password: string; roles: Role[] }) =>
      (await api.post<User>('/users', data)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['users'] }),
  });
}

export function useDeleteUser() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id: string) => (await api.delete(`/users/${id}`)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['users'] }),
  });
}

// --- Prazos ---
export function usePeriods() {
  return useQuery({ queryKey: ['periods'], queryFn: async () => (await api.get<SubmissionPeriod[]>('/periods')).data });
}

export function useCreatePeriod() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (data: { type: string; label: string; startDate: string; endDate: string }) =>
      (await api.post<SubmissionPeriod>('/periods', data)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['periods'] }),
  });
}

export function useUpdatePeriod() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, data }: { id: string; data: { type: string; label: string; startDate: string; endDate: string } }) =>
      (await api.put<SubmissionPeriod>(`/periods/${id}`, data)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['periods'] }),
  });
}

export function useDeletePeriod() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id: string) => (await api.delete(`/periods/${id}`)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['periods'] }),
  });
}

// --- Notificações (sininho) ---
/** Intervalo do polling do contador. */
export const NOTIFICATIONS_POLL_MS = 30_000;

export function useUnreadCount() {
  return useQuery({
    queryKey: ['notifications', 'count'],
    queryFn: async () => (await api.get<{ unreadCount: number }>('/notifications/unread-count')).data.unreadCount,
    refetchInterval: NOTIFICATIONS_POLL_MS,
  });
}

export function useNotifications(enabled: boolean) {
  return useQuery({
    queryKey: ['notifications', 'list'],
    queryFn: async () => (await api.get<NotificationList>('/notifications')).data,
    enabled,
  });
}

function useNotificationMutation<TInput>(fn: (input: TInput) => Promise<unknown>) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: fn,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['notifications'] }),
  });
}

export function useMarkNotificationRead() {
  return useNotificationMutation((id: string) => api.post(`/notifications/${id}/read`));
}

export function useMarkAllNotificationsRead() {
  return useNotificationMutation(() => api.post('/notifications/read-all'));
}
