import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from './client';
import { Keyword, Role, Settings, SubmissionPeriod, Tcc, ThematicArea, User, UserSummary } from './types';

// --- TCC ---
/** TCCs visíveis com um papel: os próprios, os orientados, os das bancas ou todos. */
export function useTccs(as: Role) {
  return useQuery({
    queryKey: ['tccs', as],
    queryFn: async () => (await api.get<Tcc[]>('/tcc', { params: { as } })).data,
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
