import axios, { AxiosError, InternalAxiosRequestConfig } from 'axios';
import { session } from '../auth/session';
import { AuthResponse } from './types';

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  // Envia o cookie de refresh nas rotas /auth
  withCredentials: true,
});

api.interceptors.request.use((config) => {
  const token = session.getToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Várias requisições podem receber 401 ao mesmo tempo quando o token vence:
// todas esperam o mesmo refresh em vez de disparar um cada.
let refreshing: Promise<AuthResponse> | null = null;

export function refreshSession(): Promise<AuthResponse> {
  refreshing ??= api
    .post<AuthResponse>('/auth/refresh')
    .then(({ data }) => {
      session.setToken(data.accessToken);
      return data;
    })
    .finally(() => {
      refreshing = null;
    });
  return refreshing;
}

type RetriableRequest = InternalAxiosRequestConfig & { _retried?: boolean };

api.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const original = error.config as RetriableRequest | undefined;
    const isAuthCall = original?.url?.startsWith('/auth/');

    if (error.response?.status !== 401 || !original || original._retried || isAuthCall) {
      throw error;
    }

    original._retried = true;
    try {
      await refreshSession();
    } catch {
      session.expire();
      throw error;
    }
    return api(original);
  },
);

/** Extrai a mensagem de erro da API ({ statusCode, message }), que pode ser uma lista. */
export function apiErrorMessage(error: unknown, fallback: string): string {
  const message = (error as AxiosError<{ message?: string | string[] }>)?.response?.data?.message;
  if (Array.isArray(message)) return message.join(' ');
  return message || fallback;
}
