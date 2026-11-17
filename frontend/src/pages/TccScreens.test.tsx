import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ReactNode } from 'react';
import { StudentHome } from './student/StudentHome';
import { EvaluatorHome } from './evaluator/EvaluatorHome';
import { EvaluatorTccDetail } from './evaluator/EvaluatorTccDetail';
import { mockApi } from '../test/mockApi';
import { makeTcc } from '../test/fixtures';

function renderWithProviders(ui: ReactNode, path = '/') {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={[path]}>{ui}</MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('tela do aluno', () => {
  it('sem TCC, mostra o cadastro', async () => {
    mockApi(() => ({ status: 200, data: [] }));

    renderWithProviders(<StudentHome />);

    expect(await screen.findByRole('heading', { name: 'Cadastrar meu TCC' })).toBeInTheDocument();
  });

  it('com correções pedidas, mostra os comentários da banca e o botão de reenviar', async () => {
    const tcc = makeTcc({
      status: 'CORRECTIONS_REQUESTED',
      board: [
        { id: 'b1', evaluator: { id: 'e1', name: 'Arthur', email: 'x' }, verdict: 'CORRECTIONS_REQUESTED', comment: 'Revisar a metodologia', isMe: false },
        { id: 'b2', evaluator: { id: 'e2', name: 'Alice', email: 'y' }, verdict: 'APPROVED', comment: null, isMe: false },
      ],
      permissions: { edit: true, upload: true, submit: true, advisorReview: false, evaluate: false, manageBoard: false },
    });
    mockApi((config) => (config.url === '/tcc' ? { status: 200, data: [tcc] } : config.url === '/tcc/t1' ? { status: 200, data: tcc } : { status: 200, data: [] }));

    renderWithProviders(<StudentHome />);

    expect(await screen.findByText('Revisar a metodologia')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Reenviar TCC' })).toBeEnabled();
  });

  it('mostra a mensagem da API quando a submissão é recusada', async () => {
    const tcc = makeTcc({ permissions: { edit: true, upload: true, submit: true, advisorReview: false, evaluate: false, manageBoard: false } });
    mockApi((config) => {
      if (config.url === '/tcc/t1/submit') return { status: 409, data: { statusCode: 409, message: 'Envie o PDF do TCC antes de submeter.' } };
      if (config.url === '/tcc') return { status: 200, data: [tcc] };
      if (config.url === '/tcc/t1') return { status: 200, data: tcc };
      return { status: 200, data: [] };
    });
    renderWithProviders(<StudentHome />);

    await userEvent.setup().click(await screen.findByRole('button', { name: 'Submeter TCC' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Envie o PDF do TCC antes de submeter.');
  });
});

describe('telas do avaliador', () => {
  it('separa os TCCs que aguardam o meu parecer', async () => {
    const waiting = makeTcc({
      id: 't1',
      title: 'Aguarda você',
      status: 'SENT_TO_EVALUATION',
      board: [{ id: 'b1', evaluator: { id: 'me', name: 'Eu', email: 'x' }, verdict: 'PENDING', comment: null, isMe: true }],
      permissions: { edit: false, upload: false, submit: false, advisorReview: false, evaluate: true, manageBoard: false },
    });
    const done = makeTcc({ id: 't2', title: 'Já avaliado', status: 'ADVISOR_FINAL_REVIEW' });
    mockApi(() => ({ status: 200, data: [waiting, done] }));

    renderWithProviders(<EvaluatorHome />);

    const waitingSection = (await screen.findByRole('heading', { name: 'Aguardando seu parecer' })).parentElement!;
    expect(waitingSection).toHaveTextContent('Aguarda você');
    expect(waitingSection).not.toHaveTextContent('Já avaliado');
  });

  it('envia o parecer com o comentário', async () => {
    const tcc = makeTcc({
      status: 'SENT_TO_EVALUATION',
      board: [{ id: 'b1', evaluator: { id: 'me', name: 'Eu', email: 'x' }, verdict: 'PENDING', comment: null, isMe: true }],
      permissions: { edit: false, upload: false, submit: false, advisorReview: false, evaluate: true, manageBoard: false },
    });
    const calls = mockApi((config) => ({ status: 200, data: tcc }));
    renderWithProviders(
      <Routes>
        <Route path="/evaluator/:id" element={<EvaluatorTccDetail />} />
      </Routes>,
      '/evaluator/t1',
    );
    const user = userEvent.setup();

    await user.type(await screen.findByLabelText(/Comentário/), 'Faltam referências');
    await user.click(screen.getByRole('button', { name: 'Pedir correções' }));

    const sent = calls.find((c) => c.url === '/tcc/t1/evaluate');
    expect(JSON.parse(sent!.data)).toEqual({ verdict: 'CORRECTIONS_REQUESTED', comment: 'Faltam referências' });
  });
});
