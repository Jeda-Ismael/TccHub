import { AxiosAdapter, AxiosError, AxiosResponse, InternalAxiosRequestConfig } from 'axios';
import { api } from '../api/client';
import { session } from '../auth/session';
import { AuthResponse, Role } from '../api/types';

type Handler = (config: InternalAxiosRequestConfig) => { status: number; data?: unknown; headers?: Record<string, string> };

/**
 * Substitui o transporte HTTP do axios: cada chamada vai para `handler`,
 * que responde com status, corpo e (opcional) cabeçalhos. Devolve a lista de chamadas feitas.
 */
export function mockApi(handler: Handler) {
  const calls: InternalAxiosRequestConfig[] = [];
  const adapter: AxiosAdapter = async (config) => {
    calls.push(config);
    const { status, data, headers = {} } = handler(config);
    const response: AxiosResponse = { data, status, statusText: String(status), headers, config };
    if (status >= 400) {
      throw new AxiosError(`HTTP ${status}`, String(status), config, null, response);
    }
    return response;
  };
  api.defaults.adapter = adapter;
  session.setToken(null);

  return calls;
}

export function authResponse(roles: Role[], token = 'token-1', name = 'Paulo Professor'): AuthResponse {
  return {
    accessToken: token,
    user: { id: 'u1', name, email: 'paulo@hubtcc.dev', roles, createdAt: '2026-10-01T00:00:00+00:00' },
  };
}
