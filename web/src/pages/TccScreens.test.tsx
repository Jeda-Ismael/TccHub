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
import { makeTcc, perms } from '../test/fixtures';
import { Toaster } from '../components/Toaster';

function renderWithProviders(ui: ReactNode, path = '/') {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={[path]}>
        {ui}
        <Toaster />
      </MemoryRouter>
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
      permissions: perms({ edit: true, upload: true, submit: true }),
    });
    mockApi((config) => (config.url === '/tcc' ? { status: 200, data: [tcc] } : config.url === '/tcc/t1' ? { status: 200, data: tcc } : { status: 200, data: [] }));

    renderWithProviders(<StudentHome />);

    expect(await screen.findByText('Revisar a metodologia')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Reenviar TCC' })).toBeEnabled();
  });

  it('depois do rascunho, os dados ficam fechados e o histórico mostra só os últimos passos', async () => {
    const history = Array.from({ length: 6 }, (_, i) => ({
      id: `h${i}`,
      fromStatus: null,
      toStatus: 'SUBMITTED' as const,
      transition: 'submit',
      comment: `passo ${i + 1}`,
      changedBy: null,
      createdAt: `2026-10-0${i + 1}T10:00:00-03:00`,
    }));
    const tcc = makeTcc({ status: 'CORRECTIONS_REQUESTED', statusHistory: history, permissions: perms({ edit: true, upload: true, submit: true }) });
    mockApi((config) => (config.url === '/tcc' ? { status: 200, data: [tcc] } : config.url === '/tcc/t1' ? { status: 200, data: tcc } : { status: 200, data: [] }));
    renderWithProviders(<StudentHome />);
    const user = userEvent.setup();

    await user.click(await screen.findByRole('button', { name: 'Editar título, resumo e orientador' }));
    expect(screen.getByLabelText('Título')).toHaveValue('Detecção de fraudes');

    expect(screen.queryByText('"passo 1"')).not.toBeInTheDocument();
    expect(screen.getByText('"passo 6"')).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Ver histórico completo (6)' }));
    expect(screen.getByText('"passo 1"')).toBeInTheDocument();
  });

  it('mostra a mensagem da API quando a submissão é recusada', async () => {
    const tcc = makeTcc({ permissions: perms({ edit: true, upload: true, submit: true }) });
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

describe('envio do PDF pelo aluno', () => {
  const pdf = () => new File(['%PDF-1.4'], 'tcc-final.pdf', { type: 'application/pdf' });

  function setup(status: 'DRAFT' | 'CORRECTIONS_REQUESTED' = 'CORRECTIONS_REQUESTED') {
    const tcc = makeTcc({ status, permissions: perms({ edit: true, upload: true, submit: true }) });
    const calls = mockApi((config) => {
      if (config.url === '/tcc/t1/document') return { status: 201, data: { ...tcc, document: { version: 2, originalName: 'tcc-final.pdf', sizeBytes: 8, uploadedAt: '2026-11-16T10:00:00-03:00', downloadUrl: '/x' } } };
      if (config.url === '/tcc/t1/submit') return { status: 200, data: tcc };
      if (config.url === '/tcc') return { status: 200, data: [tcc] };
      if (config.url === '/tcc/t1') return { status: 200, data: tcc };
      return { status: 200, data: [] };
    });
    renderWithProviders(<StudentHome />);
    return calls;
  }

  it('envia o PDF e reenvia o TCC num clique só', async () => {
    const calls = setup();
    const user = userEvent.setup();

    await user.upload(await screen.findByLabelText('Arquivo PDF'), pdf());
    expect(screen.getByText('tcc-final.pdf')).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Enviar PDF e reenviar TCC' }));

    expect(await screen.findByText('TCC reenviado ao orientador.')).toBeInTheDocument();
    expect(calls.map((c) => c.url).filter((u) => u === '/tcc/t1/document' || u === '/tcc/t1/submit')).toEqual(['/tcc/t1/document', '/tcc/t1/submit']);
  });

  it('pode só salvar o PDF, sem submeter', async () => {
    const calls = setup('DRAFT');
    const user = userEvent.setup();

    await user.upload(await screen.findByLabelText('Arquivo PDF'), pdf());
    await user.click(screen.getByRole('button', { name: 'Só salvar o PDF' }));

    expect(await screen.findByText('PDF salvo (versão 2).')).toBeInTheDocument();
    expect(calls.some((c) => c.url === '/tcc/t1/submit')).toBe(false);
  });

  it('recusa arquivo que não é PDF antes de enviar', async () => {
    setup();
    const user = userEvent.setup({ applyAccept: false });

    await user.upload(await screen.findByLabelText('Arquivo PDF'), new File(['x'], 'foto.png', { type: 'image/png' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Escolha um arquivo PDF.');
    expect(screen.getByRole('button', { name: 'Reenviar TCC' })).toBeInTheDocument();
  });

  it('deixa trocar o arquivo escolhido', async () => {
    setup();
    const user = userEvent.setup();

    await user.upload(await screen.findByLabelText('Arquivo PDF'), pdf());
    await user.click(screen.getByRole('button', { name: 'Trocar arquivo' }));

    expect(screen.queryByText('tcc-final.pdf')).not.toBeInTheDocument();
    expect(screen.getByText('Escolha o PDF')).toBeInTheDocument();
  });
});

describe('telas do avaliador', () => {
  it('separa os TCCs que aguardam o meu parecer', async () => {
    const waiting = makeTcc({
      id: 't1',
      title: 'Aguarda você',
      status: 'SENT_TO_EVALUATION',
      board: [{ id: 'b1', evaluator: { id: 'me', name: 'Eu', email: 'x' }, verdict: 'PENDING', comment: null, isMe: true }],
      permissions: perms({ evaluate: true }),
    });
    const done = makeTcc({ id: 't2', title: 'Já avaliado', status: 'ADVISOR_FINAL_REVIEW' });
    mockApi(() => ({ status: 200, data: [waiting, done] }));

    renderWithProviders(<EvaluatorHome />);

    const waitingSection = (await screen.findByRole('heading', { name: 'Aguardando você' })).parentElement!;
    expect(waitingSection).toHaveTextContent('Aguarda você');
    expect(waitingSection).not.toHaveTextContent('Já avaliado');
  });

  it('envia o parecer com o comentário', async () => {
    const tcc = makeTcc({
      status: 'SENT_TO_EVALUATION',
      board: [{ id: 'b1', evaluator: { id: 'me', name: 'Eu', email: 'x' }, verdict: 'PENDING', comment: null, isMe: true }],
      permissions: perms({ evaluate: true }),
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
