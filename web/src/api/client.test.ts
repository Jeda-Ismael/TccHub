import { describe, expect, it, vi } from 'vitest';
import { api, apiErrorMessage } from './client';
import { session } from '../auth/session';
import { authResponse, mockApi } from '../test/mockApi';

describe('cliente da API', () => {
  it('envia o token de acesso no header Authorization', async () => {
    const calls = mockApi(() => ({ status: 200, data: {} }));
    session.setToken('abc');

    await api.get('/me');

    expect(calls[0].headers.Authorization).toBe('Bearer abc');
  });

  it('renova a sessão ao receber 401 e repete a requisição com o token novo', async () => {
    const calls = mockApi((config) => {
      if (config.url === '/auth/refresh') return { status: 200, data: authResponse(['ADVISOR'], 'token-novo') };
      return config.headers.Authorization === 'Bearer token-novo' ? { status: 200, data: { ok: true } } : { status: 401 };
    });
    session.setToken('token-vencido');

    const { data } = await api.get('/me');

    expect(data).toEqual({ ok: true });
    expect(calls.map((c) => c.url)).toEqual(['/me', '/auth/refresh', '/me']);
    expect(session.getToken()).toBe('token-novo');
  });

  it('faz um único refresh quando várias requisições vencem juntas', async () => {
    const calls = mockApi((config) => {
      if (config.url === '/auth/refresh') return { status: 200, data: authResponse(['ADVISOR'], 'token-novo') };
      return config.headers.Authorization === 'Bearer token-novo' ? { status: 200 } : { status: 401 };
    });

    await Promise.all([api.get('/a'), api.get('/b'), api.get('/c')]);

    expect(calls.filter((c) => c.url === '/auth/refresh')).toHaveLength(1);
  });

  it('encerra a sessão quando o refresh é recusado', async () => {
    mockApi(() => ({ status: 401 }));
    const expired = vi.fn();
    const unsubscribe = session.onExpired(expired);
    session.setToken('token-vencido');

    await expect(api.get('/me')).rejects.toThrow();

    expect(expired).toHaveBeenCalledOnce();
    expect(session.getToken()).toBeNull();
    unsubscribe();
  });

  it('não tenta refresh para as próprias rotas de autenticação', async () => {
    const calls = mockApi(() => ({ status: 401, data: { statusCode: 401, message: 'E-mail ou senha incorretos.' } }));

    await expect(api.post('/auth/login', {})).rejects.toThrow();

    expect(calls).toHaveLength(1);
  });
});

describe('apiErrorMessage', () => {
  it('usa a mensagem da API, juntando listas de validação', async () => {
    mockApi(() => ({ status: 422, data: { statusCode: 422, message: ['Informe o nome.', 'E-mail inválido.'] } }));

    const error = await api.post('/x').catch((e: unknown) => e);

    expect(apiErrorMessage(error, 'falhou')).toBe('Informe o nome. E-mail inválido.');
  });

  it('cai no texto padrão quando não há resposta da API', () => {
    expect(apiErrorMessage(new Error('rede'), 'Sem conexão')).toBe('Sem conexão');
  });
});
