import { describe, expect, it } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { AuthProvider } from './AuthContext';
import { ProtectedRoute } from './ProtectedRoute';
import { Layout } from '../components/Layout';
import { Dashboard } from '../pages/Dashboard';
import { Login } from '../pages/auth/Login';
import { Register } from '../pages/auth/Register';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { authResponse, mockApi as baseMockApi } from '../test/mockApi';

/** O sininho do topo consulta o contador; aqui ele responde sempre "nenhum aviso". */
const mockApi: typeof baseMockApi = (handler) =>
  baseMockApi((config) => (config.url?.startsWith('/notifications') ? { status: 200, data: { unreadCount: 0, items: [] } } : handler(config)));

function renderApp(initialPath: string) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={client}>
      <AuthProvider>
        <MemoryRouter initialEntries={[initialPath]}>
          <Routes>
            <Route path="/login" element={<Login />} />
            <Route path="/register" element={<Register />} />
            <Route element={<Layout />}>
              <Route element={<ProtectedRoute />}>
                <Route path="/" element={<Dashboard />} />
              </Route>
              <Route element={<ProtectedRoute allow={['STUDENT']} />}>
                <Route path="/student" element={<p>Tela do aluno</p>} />
              </Route>
              <Route element={<ProtectedRoute allow={['ADVISOR']} />}>
                <Route path="/advisor" element={<p>Tela do orientador</p>} />
              </Route>
              <Route element={<ProtectedRoute allow={['EVALUATOR']} />}>
                <Route path="/evaluator" element={<p>Tela do avaliador</p>} />
              </Route>
              <Route element={<ProtectedRoute allow={['COORDINATION']} />}>
                <Route path="/coordination" element={<p>Tela da coordenação</p>} />
              </Route>
            </Route>
          </Routes>
        </MemoryRouter>
      </AuthProvider>
    </QueryClientProvider>,
  );
}

describe('fluxo de autenticação', () => {
  it('sem sessão, manda para o login', async () => {
    mockApi(() => ({ status: 401 }));

    renderApp('/advisor');

    expect(await screen.findByRole('heading', { name: 'Entrar' })).toBeInTheDocument();
  });

  it('faz login e abre a tela do papel do usuário', async () => {
    const calls = mockApi((config) =>
      config.url === '/auth/login' ? { status: 200, data: authResponse(['ADVISOR']) } : { status: 401 },
    );
    renderApp('/');
    const user = userEvent.setup();

    await user.type(await screen.findByLabelText('E-mail'), 'paulo@hubtcc.dev');
    await user.type(screen.getByLabelText('Senha'), '123456');
    await user.click(screen.getByRole('button', { name: 'Entrar' }));

    expect(await screen.findByText('Tela do orientador')).toBeInTheDocument();
    expect(JSON.parse(calls.find((c) => c.url === '/auth/login')!.data)).toEqual({ email: 'paulo@hubtcc.dev', password: '123456' });
  });

  it('mostra a mensagem de erro da API', async () => {
    mockApi((config) =>
      config.url === '/auth/login' ? { status: 401, data: { statusCode: 401, message: 'E-mail ou senha incorretos.' } } : { status: 401 },
    );
    renderApp('/login');
    const user = userEvent.setup();

    await user.type(await screen.findByLabelText('E-mail'), 'x@hubtcc.dev');
    await user.type(screen.getByLabelText('Senha'), 'errada');
    await user.click(screen.getByRole('button', { name: 'Entrar' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('E-mail ou senha incorretos.');
  });

  it('restaura a sessão pelo cookie ao abrir o app', async () => {
    mockApi((config) => (config.url === '/auth/refresh' ? { status: 200, data: authResponse(['STUDENT'], 't', 'Ana Aluna') } : { status: 200 }));

    renderApp('/');

    expect(await screen.findByText('Tela do aluno')).toBeInTheDocument();
    expect(screen.getByText('Ana Aluna')).toBeInTheDocument();
    expect(screen.queryByLabelText('Ver como')).not.toBeInTheDocument();
  });

  it('com vários papéis, troca de tela pelo seletor e lembra a escolha', async () => {
    mockApi((config) =>
      config.url === '/auth/refresh' ? { status: 200, data: authResponse(['ADVISOR', 'EVALUATOR', 'COORDINATION']) } : { status: 200 },
    );
    renderApp('/');
    const user = userEvent.setup();

    expect(await screen.findByText('Tela do orientador')).toBeInTheDocument();

    await user.selectOptions(screen.getByLabelText('Ver como'), 'COORDINATION');

    expect(await screen.findByText('Tela da coordenação')).toBeInTheDocument();
    expect(localStorage.getItem('hubtcc_active_role')).toBe('COORDINATION');
  });

  it('abre direto no último papel escolhido', async () => {
    localStorage.setItem('hubtcc_active_role', 'EVALUATOR');
    mockApi((config) =>
      config.url === '/auth/refresh' ? { status: 200, data: authResponse(['ADVISOR', 'EVALUATOR']) } : { status: 200 },
    );

    renderApp('/');

    expect(await screen.findByText('Tela do avaliador')).toBeInTheDocument();
  });

  it('bloqueia telas de papéis que o usuário não tem', async () => {
    mockApi((config) => (config.url === '/auth/refresh' ? { status: 200, data: authResponse(['STUDENT']) } : { status: 200 }));

    renderApp('/coordination');

    expect(await screen.findByText('Tela do aluno')).toBeInTheDocument();
  });

  it('sair encerra a sessão e volta ao login', async () => {
    const calls = mockApi((config) => (config.url === '/auth/refresh' ? { status: 200, data: authResponse(['ADVISOR']) } : { status: 200 }));
    renderApp('/');
    const user = userEvent.setup();

    await user.click(await screen.findByRole('button', { name: 'Sair' }));

    expect(await screen.findByRole('heading', { name: 'Entrar' })).toBeInTheDocument();
    expect(calls.some((c) => c.url === '/auth/logout')).toBe(true);
  });

  it('cadastra um aluno e já entra no sistema', async () => {
    const calls = mockApi((config) =>
      config.url === '/auth/register' ? { status: 201, data: authResponse(['STUDENT'], 't', 'Bruna') } : { status: 401 },
    );
    renderApp('/register');
    const user = userEvent.setup();

    await user.type(await screen.findByLabelText('Nome'), 'Bruna');
    await user.type(screen.getByLabelText('E-mail'), 'bruna@hubtcc.dev');
    await user.type(screen.getByLabelText('Senha'), 'senha-forte');
    await user.click(screen.getByRole('button', { name: 'Criar conta' }));

    await waitFor(() => expect(screen.getByText('Tela do aluno')).toBeInTheDocument());
    expect(JSON.parse(calls.find((c) => c.url === '/auth/register')!.data)).toEqual({
      name: 'Bruna',
      email: 'bruna@hubtcc.dev',
      password: 'senha-forte',
    });
  });
});
