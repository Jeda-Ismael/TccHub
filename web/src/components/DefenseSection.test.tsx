import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { DefenseSection, formatGrade } from './DefenseSection';
import { formatDateTime, formatLongDateTime } from '../utils/dates';
import { Defense, Tcc } from '../api/types';
import { makeTcc, perms } from '../test/fixtures';
import { mockApi } from '../test/mockApi';
import { Toaster } from './Toaster';

function defense(overrides: Partial<Defense> = {}): Defense {
  return {
    attempt: 1,
    scheduledAt: '2026-11-20T14:00:00-03:00',
    location: 'Sala 101',
    meetingUrl: null,
    hasStarted: true,
    outcome: 'PENDING',
    projectAverage: null,
    presentationAverage: null,
    finalGrade: null,
    gradedCount: 0,
    boardSize: 2,
    grades: [],
    ...overrides,
  };
}

function renderSection(tcc: Tcc) {
  // Os avisos ficam num container à parte, para não contar como conteúdo da seção
  render(<Toaster />);
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={client}>
      <DefenseSection tcc={tcc} />
    </QueryClientProvider>,
  );
}

describe('seção da defesa', () => {
  it('formata notas com vírgula e duas casas', () => {
    expect(formatGrade(8.5)).toBe('8,50');
    expect(formatGrade(null)).toBe('—');
  });

  it('mostra a data no fuso da instituição, não no do navegador', () => {
    expect(formatLongDateTime('2026-11-20T14:00:00-03:00')).toBe('sexta-feira, 20/11/2026 às 14:00');
    expect(formatDateTime('2026-11-20T23:30:00-03:00')).toBe('20/11/2026, 23:30');
  });

  it('não aparece antes de existir defesa', () => {
    const { container } = renderSection(makeTcc());

    expect(container).toBeEmptyDOMElement();
  });

  it('avisa quando ainda falta agendar', () => {
    renderSection(makeTcc({ status: 'AWAITING_DEFENSE', defense: defense({ scheduledAt: null, location: null, hasStarted: false }) }));

    expect(screen.getByText(/Aguardando o agendamento/)).toBeInTheDocument();
  });

  it('mostra o resultado e as notas quando a defesa encerra', () => {
    renderSection(
      makeTcc({
        status: 'APPROVED',
        defense: defense({
          outcome: 'PASSED',
          projectAverage: 7.5,
          presentationAverage: 9.5,
          finalGrade: 8.5,
          gradedCount: 2,
          grades: [
            { evaluator: { id: 'e1', name: 'Arthur', email: 'x' }, projectGrade: 8, presentationGrade: 9, comment: null, isMe: false },
            { evaluator: { id: 'e2', name: 'Alice', email: 'y' }, projectGrade: 7, presentationGrade: 10, comment: 'Boa', isMe: false },
          ],
        }),
      }),
    );

    expect(screen.getByText('Média final 8,50')).toBeInTheDocument();
    expect(screen.getByText('Aprovado')).toBeInTheDocument();
    expect(screen.getAllByRole('row')).toHaveLength(3);
  });

  it('o avaliador lança as notas aceitando vírgula', async () => {
    const tcc = makeTcc({ status: 'AWAITING_DEFENSE', defense: defense(), permissions: perms({ gradeDefense: true }) });
    const calls = mockApi(() => ({ status: 200, data: { ...tcc, defense: defense({ gradedCount: 1 }) } }));
    renderSection(tcc);
    const user = userEvent.setup();

    await user.type(screen.getByLabelText('Projeto'), '8,5');
    await user.type(screen.getByLabelText('Apresentação'), '9');
    await user.click(screen.getByRole('button', { name: 'Lançar notas' }));

    expect(JSON.parse(calls[0].data)).toEqual({ projectGrade: 8.5, presentationGrade: 9 });
    expect(await screen.findByText('Notas registradas.')).toBeInTheDocument();
  });

  it('a coordenação decide depois de uma defesa abaixo da mínima', async () => {
    const tcc = makeTcc({
      status: 'AWAITING_DECISION',
      defense: defense({ outcome: 'FAILED', finalGrade: 5.38 }),
      permissions: perms({ decide: true }),
    });
    const calls = mockApi(() => ({ status: 200, data: tcc }));
    renderSection(tcc);
    const user = userEvent.setup();

    await user.type(screen.getByLabelText(/Justificativa/), 'Nova chance após ajustes');
    await user.click(screen.getByRole('button', { name: 'Permitir nova defesa' }));

    expect(calls[0].url).toBe('/tcc/t1/decision');
    expect(JSON.parse(calls[0].data)).toEqual({ action: 'new_defense', comment: 'Nova chance após ajustes' });
  });

  it('agenda com data, sala e link', async () => {
    const tcc = makeTcc({ status: 'AWAITING_DEFENSE', defense: defense({ scheduledAt: null, location: null, hasStarted: false }), permissions: perms({ scheduleDefense: true }) });
    const calls = mockApi(() => ({ status: 200, data: tcc }));
    renderSection(tcc);
    const user = userEvent.setup();

    await user.type(screen.getByLabelText('Data e hora'), '2026-11-20T14:00');
    await user.type(screen.getByLabelText(/Sala/), 'Sala 101');
    await user.click(screen.getByRole('button', { name: 'Agendar' }));

    expect(JSON.parse(calls[0].data)).toEqual({ scheduledAt: '2026-11-20T14:00', location: 'Sala 101' });
  });
});
