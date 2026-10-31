import { describe, expect, it } from 'vitest';
import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { mockApi } from '../../test/mockApi';
import { makeTcc, perms } from '../../test/fixtures';
import { renderPage } from '../../test/render';
import { AdvisorHome } from './AdvisorHome';
import { AdvisorTccDetail } from './AdvisorTccDetail';

describe('orientador', () => {
  it('separa o que aguarda revisão dos demais orientandos', async () => {
    const waiting = makeTcc({ id: 't1', title: 'Precisa de revisão', status: 'SUBMITTED', permissions: perms({ advisorReview: true }) });
    const toSchedule = makeTcc({ id: 't2', title: 'Defesa sem data', status: 'AWAITING_DEFENSE', permissions: perms({ scheduleDefense: true }) });
    const other = makeTcc({ id: 't3', title: 'Em avaliação', status: 'SENT_TO_EVALUATION' });
    mockApi(() => ({ status: 200, data: [waiting, toSchedule, other] }));

    renderPage(<AdvisorHome />);

    const queue = (await screen.findByRole('heading', { name: 'Aguardando você' })).parentElement!;
    expect(queue).toHaveTextContent('Precisa de revisão');
    expect(queue).toHaveTextContent('Defesa sem data');
    expect(queue).not.toHaveTextContent('Em avaliação');
    expect(screen.getByRole('link', { name: /Em avaliação/ })).toHaveAttribute('href', '/advisor/t3');
  });

  it('sem orientandos', async () => {
    mockApi(() => ({ status: 200, data: [] }));

    renderPage(<AdvisorHome />);

    expect(await screen.findByText('Nenhum TCC sob sua orientação ainda.')).toBeInTheDocument();
  });

  it('todos aguardando revisão', async () => {
    mockApi(() => ({ status: 200, data: [makeTcc({ permissions: perms({ advisorReview: true }) })] }));

    renderPage(<AdvisorHome />);

    expect(await screen.findByText('Todos os seus orientandos estão aguardando revisão.')).toBeInTheDocument();
  });

  it('aprova e envia à banca', async () => {
    const tcc = makeTcc({
      status: 'SUBMITTED',
      permissions: perms({ advisorReview: true }),
      statusHistory: [
        { id: 'h1', fromStatus: null, toStatus: 'DRAFT', transition: 'create', comment: 'TCC criado', changedBy: { id: 's1', name: 'Ana', email: 'a' }, createdAt: '2026-10-01T10:00:00-03:00' },
        { id: 'h2', fromStatus: 'DRAFT', toStatus: 'SUBMITTED', transition: 'submit', comment: null, changedBy: null, createdAt: '2026-10-02T11:30:00-03:00' },
      ],
    });
    const calls = mockApi((config) => (config.method === 'post' ? { status: 200, data: { ...tcc, status: 'SENT_TO_EVALUATION' } } : { status: 200, data: tcc }));
    const user = userEvent.setup();
    renderPage(<AdvisorTccDetail />, { path: `/advisor/${tcc.id}`, route: '/advisor/:id' });

    expect(await screen.findByRole('heading', { name: 'Revisão do orientador' })).toBeInTheDocument();
    // Histórico do mais recente para o mais antigo
    expect(screen.getByText('02/10/2026, 11:30')).toBeInTheDocument();
    expect(screen.getByText('"TCC criado"')).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Aprovar e enviar à banca' }));

    await waitFor(() => expect(calls.some((c) => c.method === 'post' && c.url === `/tcc/${tcc.id}/advisor-review`)).toBe(true));
    expect(JSON.parse(calls.find((c) => c.method === 'post')!.data)).toEqual({ approve: true });
  });

  it('revisão final: devolver mostra o erro da API', async () => {
    const tcc = makeTcc({ status: 'ADVISOR_FINAL_REVIEW', permissions: perms({ advisorReview: true }) });
    mockApi((config) =>
      config.method === 'post' ? { status: 422, data: { statusCode: 422, message: 'Explique o que precisa ser corrigido.' } } : { status: 200, data: tcc },
    );
    const user = userEvent.setup();
    renderPage(<AdvisorTccDetail />, { path: `/advisor/${tcc.id}`, route: '/advisor/:id' });

    await user.click(await screen.findByRole('button', { name: 'Devolver para correções' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Explique o que precisa ser corrigido.');
  });
});
