import { describe, expect, it } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { AuthProvider } from '../auth/AuthContext';
import { AppNotification } from '../api/types';
import { authResponse, mockApi } from '../test/mockApi';
import { NotificationBell, notificationPath } from './NotificationBell';

const notification = (overrides: Partial<AppNotification> = {}): AppNotification => ({
  id: 'n1',
  type: 'EVALUATION_OPEN',
  title: 'TCC aguardando o seu parecer',
  body: '"Detecção de fraudes", de Ana, foi liberado para a banca.',
  tccId: 't1',
  as: 'EVALUATOR',
  read: false,
  createdAt: '2026-10-31T14:05:00-03:00',
  ...overrides,
});

function renderBell() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={client}>
      <AuthProvider>
        <MemoryRouter initialEntries={['/']}>
          <Routes>
            <Route path="/" element={<NotificationBell />} />
            <Route path="/evaluator/:id" element={<p>Tela do avaliador</p>} />
          </Routes>
        </MemoryRouter>
      </AuthProvider>
    </QueryClientProvider>,
  );
}

describe('sininho', () => {
  it('mostra o contador, abre a lista e leva ao TCC marcando como lido', async () => {
    const items = [notification(), notification({ id: 'n2', title: 'Defesa agendada', read: true, type: 'DEFENSE_SCHEDULED' })];
    const calls = mockApi((config) => {
      if (config.url === '/auth/refresh') return { status: 200, data: authResponse(['ADVISOR', 'EVALUATOR']) };
      if (config.url === '/notifications/unread-count') return { status: 200, data: { unreadCount: 1 } };
      if (config.url === '/notifications') return { status: 200, data: { items, unreadCount: 1 } };
      return { status: 200, data: { unreadCount: 0 } };
    });
    const user = userEvent.setup();
    renderBell();

    await user.click(await screen.findByRole('button', { name: 'Notificações (1 não lidas)' }));

    expect(await screen.findByText('Defesa agendada')).toBeInTheDocument();
    expect(screen.getAllByText('31/10/2026, 14:05')).toHaveLength(2);

    await user.click(screen.getByText('TCC aguardando o seu parecer'));

    expect(await screen.findByText('Tela do avaliador')).toBeInTheDocument();
    await waitFor(() => expect(calls.some((c) => c.method === 'post' && c.url === '/notifications/n1/read')).toBe(true));
  });

  it('marca todas como lidas', async () => {
    const calls = mockApi((config) => {
      if (config.url === '/auth/refresh') return { status: 200, data: authResponse(['STUDENT']) };
      if (config.url === '/notifications') return { status: 200, data: { items: [notification({ as: 'STUDENT' })], unreadCount: 1 } };
      return { status: 200, data: { unreadCount: 1 } };
    });
    const user = userEvent.setup();
    renderBell();

    await user.click(await screen.findByRole('button', { name: /Notificações/ }));
    await user.click(await screen.findByRole('button', { name: 'Marcar todas como lidas' }));

    await waitFor(() => expect(calls.some((c) => c.method === 'post' && c.url === '/notifications/read-all')).toBe(true));
  });

  it('lista vazia', async () => {
    mockApi((config) => {
      if (config.url === '/auth/refresh') return { status: 200, data: authResponse(['STUDENT']) };
      if (config.url === '/notifications') return { status: 200, data: { items: [], unreadCount: 0 } };
      return { status: 200, data: { unreadCount: 0 } };
    });
    const user = userEvent.setup();
    renderBell();

    await user.click(await screen.findByRole('button', { name: 'Notificações' }));

    expect(await screen.findByText('Nenhuma notificação.')).toBeInTheDocument();
  });

  it('caminho do TCC conforme o papel', () => {
    expect(notificationPath(notification({ as: 'STUDENT' }))).toBe('/student');
    expect(notificationPath(notification({ as: 'ADVISOR' }))).toBe('/advisor/t1');
    expect(notificationPath(notification({ as: 'COORDINATION' }))).toBe('/coordination/tcc/t1');
    expect(notificationPath(notification({ tccId: null, as: null }))).toBeNull();
  });
});
