import { describe, expect, it } from 'vitest';
import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AdvisingMeeting } from '../api/types';
import { makeTcc, perms } from '../test/fixtures';
import { mockApi } from '../test/mockApi';
import { renderPage } from '../test/render';
import { MeetingsCard } from './MeetingsCard';
import { MilestonesCard } from './MilestonesCard';
import { Milestones } from '../pages/coordination/Milestones';

function meeting(overrides: Partial<AdvisingMeeting> = {}): AdvisingMeeting {
  return {
    id: 'm1',
    heldOn: '2026-11-10',
    summary: 'Definimos o recorte.',
    nextSteps: 'Ler 3 artigos',
    advisor: { id: 'a1', name: 'Olga Orientadora', email: 'o' },
    confirmedAt: null,
    createdAt: '2026-11-10T15:00:00-03:00',
    permissions: { confirm: false, delete: false },
    ...overrides,
  };
}

describe('reuniões de orientação', () => {
  it('o orientador registra uma reunião', async () => {
    const calls = mockApi((config) => ({ status: config.method === 'post' ? 201 : 200, data: config.method === 'post' ? meeting() : [] }));
    renderPage(<MeetingsCard tcc={makeTcc({ permissions: perms({ recordMeeting: true }) })} />);
    const user = userEvent.setup();

    expect(await screen.findByText('Nenhuma reunião registrada ainda.')).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Registrar reunião' }));
    await user.clear(screen.getByLabelText('Data'));
    await user.type(screen.getByLabelText('Data'), '2026-11-10');
    expect(screen.getByText('10/11/2026')).toBeInTheDocument();
    await user.type(screen.getByLabelText('O que foi conversado'), 'Definimos o recorte.');
    await user.click(screen.getByRole('button', { name: 'Registrar' }));

    expect(await screen.findByText('Reunião registrada. O aluno vai receber um aviso para confirmar.')).toBeInTheDocument();
    const post = calls.find((c) => c.method === 'post')!;
    expect(post.url).toBe('/tcc/t1/meetings');
    expect(JSON.parse(post.data)).toEqual({ heldOn: '2026-11-10', summary: 'Definimos o recorte.' });
  });

  it('o aluno confirma; o orientador pode apagar a que não foi confirmada', async () => {
    const calls = mockApi((config) =>
      config.method === 'get'
        ? { status: 200, data: [meeting({ permissions: { confirm: true, delete: false } }), meeting({ id: 'm2', heldOn: '2026-10-01', nextSteps: null, confirmedAt: '2026-10-02T10:00:00-03:00' })] }
        : { status: 200, data: meeting() },
    );
    renderPage(<MeetingsCard tcc={makeTcc()} />);
    const user = userEvent.setup();

    expect(await screen.findByText('Há 1 reunião esperando a sua confirmação.')).toBeInTheDocument();
    expect(screen.getByText('Confirmada pelo aluno')).toBeInTheDocument();
    expect(screen.getByText('Ler 3 artigos')).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Confirmar que aconteceu' }));

    expect(await screen.findByText('Reunião confirmada.')).toBeInTheDocument();
    expect(calls.some((c) => c.method === 'post' && c.url === '/tcc/t1/meetings/m1/confirm')).toBe(true);
  });

  it('mostra o erro da API e some quando não há nada para ver', async () => {
    mockApi((config) =>
      config.method === 'get'
        ? { status: 200, data: [meeting({ permissions: { confirm: false, delete: true } })] }
        : { status: 422, data: { statusCode: 422, message: 'O aluno já confirmou esta reunião; ela não pode mais ser apagada.' } },
    );
    renderPage(<MeetingsCard tcc={makeTcc({ permissions: perms({ recordMeeting: true }) })} />);
    await userEvent.setup().click(await screen.findByRole('button', { name: 'Apagar' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('O aluno já confirmou esta reunião');
  });

  it('sem reuniões e sem poder registrar, não aparece', async () => {
    const calls = mockApi(() => ({ status: 200, data: [] }));
    const { container } = renderPage(<MeetingsCard tcc={makeTcc()} />);
    await waitFor(() => expect(calls.length).toBe(1));
    expect(container.querySelector('.card')).toBeNull();
  });
});

describe('cronograma', () => {
  const milestones = [
    { id: 'k1', name: 'Projeto', description: 'Problema e método', dueDate: '2026-09-30', status: 'DONE' },
    { id: 'k2', name: 'Qualificação', description: null, dueDate: '2026-11-30', status: 'NEXT' },
    { id: 'k3', name: 'Versão final', description: null, dueDate: '2026-12-15', status: 'UPCOMING' },
  ];

  it('mostra as etapas com a próxima entrega em destaque', async () => {
    mockApi(() => ({ status: 200, data: milestones }));
    renderPage(<MilestonesCard />);

    expect(await screen.findByText('Próxima entrega')).toBeInTheDocument();
    expect(screen.getByText('Qualificação').closest('li')).toHaveClass('milestone-next');
    expect(screen.getByText('30/09/2026')).toBeInTheDocument();
    expect(screen.getByText('Prazo passou')).toBeInTheDocument();
  });

  it('a coordenação cria, edita e exclui etapas', async () => {
    const calls = mockApi((config) => ({ status: 200, data: config.method === 'get' ? milestones : milestones[1] }));
    renderPage(<Milestones />);
    const user = userEvent.setup();

    expect(await screen.findByText('Próxima')).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Nova etapa' }));
    await user.type(screen.getByLabelText('Nome (ex: Qualificação)'), 'Entrega do pôster');
    await user.type(screen.getByLabelText('Data limite'), '2026-12-20');
    await user.click(screen.getByRole('button', { name: 'Criar etapa' }));
    await waitFor(() => expect(calls.some((c) => c.method === 'post' && c.url === '/milestones')).toBe(true));
    expect(JSON.parse(calls.find((c) => c.method === 'post')!.data)).toEqual({ name: 'Entrega do pôster', dueDate: '2026-12-20' });

    await user.click(screen.getByRole('button', { name: 'Editar etapa Projeto' }));
    expect(screen.getByLabelText('O que entregar (opcional)')).toHaveValue('Problema e método');
    await user.click(screen.getByRole('button', { name: 'Salvar etapa' }));
    await waitFor(() => expect(calls.some((c) => c.method === 'put' && c.url === '/milestones/k1')).toBe(true));

    await user.click(screen.getByRole('button', { name: 'Excluir etapa Versão final' }));
    await user.click(screen.getByRole('button', { name: 'Confirmar' }));
    await waitFor(() => expect(calls.some((c) => c.method === 'delete' && c.url === '/milestones/k3')).toBe(true));
  });
});
