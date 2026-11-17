import { describe, expect, it } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ReactNode } from 'react';
import { RepositoryPage, RepositoryTcc } from '../../api/types';
import { mockApi } from '../../test/mockApi';
import { makeTcc, perms } from '../../test/fixtures';
import { PublicationCard } from '../../components/PublicationCard';
import { RepositoryDetail } from './RepositoryDetail';
import { RepositorySearch } from './RepositorySearch';

function renderWithProviders(ui: ReactNode, path = '/repository') {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={[path]}>
        <Routes>
          <Route path="/repository" element={ui} />
          <Route path="/repository/:id" element={ui} />
          <Route path="/tcc" element={ui} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

const item = (overrides: Partial<RepositoryTcc> = {}): RepositoryTcc => ({
  id: 'r1',
  title: 'Detecção de fraudes',
  abstract: 'Modelos de aprendizado de máquina.',
  student: { id: 's1', name: 'Ana Souza' },
  advisor: { id: 'a1', name: 'Olga Lima' },
  thematicAreas: [{ id: 'ia', name: 'Inteligência Artificial' }],
  keywords: [],
  finalGrade: 8.63,
  publishedAt: '2026-10-31T15:00:00-03:00',
  ...overrides,
});

const page = (items: RepositoryTcc[], total = items.length): RepositoryPage => ({ items, total, page: 1, pageSize: 12, years: [2026] });

describe('repositório', () => {
  it('busca com texto e filtros e mostra os resultados', async () => {
    const calls = mockApi((config) => {
      if (config.url === '/repository') return { status: 200, data: page(config.params?.q ? [item()] : [item(), item({ id: 'r2', title: 'Contagem de veículos' })]) };
      if (config.url === '/thematic-areas') return { status: 200, data: [{ id: 'ia', name: 'Inteligência Artificial', tccCount: 1 }] };
      return { status: 200, data: [] };
    });
    const user = userEvent.setup();
    renderWithProviders(<RepositorySearch />);

    expect(await screen.findByText('2 TCCs encontrados')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Contagem de veículos' })).toHaveAttribute('href', '/repository/r2');

    await user.type(screen.getByRole('searchbox'), 'fraude');
    await user.click(screen.getByRole('button', { name: 'Buscar' }));

    expect(await screen.findByText('1 TCC encontrado')).toBeInTheDocument();
    expect(calls.some((c) => c.url === '/repository' && c.params?.q === 'fraude')).toBe(true);

    await user.selectOptions(await screen.findByLabelText('Área temática'), 'ia');
    await waitFor(() => expect(calls.some((c) => c.url === '/repository' && c.params?.area === 'ia' && c.params?.q === 'fraude')).toBe(true));
  });

  it('sem resultados', async () => {
    mockApi((config) => (config.url === '/repository' ? { status: 200, data: page([]) } : { status: 200, data: [] }));

    renderWithProviders(<RepositorySearch />);

    expect(await screen.findByText('Ainda não há TCCs publicados.')).toBeInTheDocument();
  });

  it('paginação', async () => {
    const calls = mockApi((config) => (config.url === '/repository' ? { status: 200, data: page([item()], 14) } : { status: 200, data: [] }));
    const user = userEvent.setup();
    renderWithProviders(<RepositorySearch />);

    expect(await screen.findByText('Página 1 de 2')).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Próxima' }));

    await waitFor(() => expect(calls.some((c) => c.url === '/repository' && c.params?.page === 2)).toBe(true));
  });

  it('página além do fim volta para a última', async () => {
    const calls = mockApi((config) =>
      config.url === '/repository' ? { status: 200, data: { ...page(config.params?.page === 3 ? [] : [item()], 14), page: config.params?.page ?? 1 } } : { status: 200, data: [] },
    );
    renderWithProviders(<RepositorySearch />, '/repository?page=3');

    expect(await screen.findByText('Página 2 de 2', {}, { timeout: 5000 })).toBeInTheDocument();
    expect(calls.some((c) => c.url === '/repository' && c.params?.page === 2)).toBe(true);
  });

  it('mantém no select um ano da URL sem TCCs', async () => {
    mockApi((config) => (config.url === '/repository' ? { status: 200, data: page([]) } : { status: 200, data: [] }));
    renderWithProviders(<RepositorySearch />, '/repository?year=2019');

    expect(await screen.findByText('Nenhum TCC encontrado com esses filtros.')).toBeInTheDocument();
    expect(screen.getByLabelText('Ano')).toHaveValue('2019');
  });

  it('detalhe com o PDF final', async () => {
    mockApi(() => ({
      status: 200,
      data: { ...item(), document: { version: 2, originalName: 'final.pdf', sizeBytes: 2048, downloadUrl: '/api/tcc/r1/documents/2/download?_hash=x' } },
    }));

    renderWithProviders(<RepositoryDetail />, '/repository/r1');

    expect(await screen.findByRole('heading', { name: 'Detecção de fraudes' })).toBeInTheDocument();
    expect(screen.getByText(/Nota final 8,63/)).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Abrir o PDF' })).toHaveAttribute('href', '/api/tcc/r1/documents/2/download?_hash=x');
  });
});

describe('publicação', () => {
  it('aluno autoriza', async () => {
    const tcc = makeTcc({ status: 'APPROVED', permissions: perms({ authorizePublication: true }) });
    const calls = mockApi(() => ({ status: 200, data: { ...tcc, publicationAuthorized: true } }));
    const user = userEvent.setup();
    renderWithProviders(<PublicationCard tcc={tcc} />, '/tcc');

    await user.click(screen.getByRole('button', { name: 'Autorizar publicação' }));

    await waitFor(() => expect(calls.some((c) => c.method === 'put' && c.url === `/tcc/${tcc.id}/publication-authorization`)).toBe(true));
    expect(JSON.parse(calls[0].data)).toEqual({ authorized: true });
  });

  it('coordenação só publica depois da autorização', () => {
    const tcc = makeTcc({ status: 'APPROVED', permissions: perms({ publish: true }) });
    mockApi(() => ({ status: 200, data: tcc }));

    const { rerender } = renderWithProviders(<PublicationCard tcc={tcc} />, '/tcc');
    expect(screen.getByRole('button', { name: 'Publicar no repositório' })).toBeDisabled();
    expect(screen.getByText(/Ainda não autorizado pelo aluno/)).toBeInTheDocument();

    const client = new QueryClient();
    rerender(
      <QueryClientProvider client={client}>
        <MemoryRouter>
          <PublicationCard tcc={{ ...tcc, publicationAuthorized: true, publishedAt: '2026-10-31T15:00:00-03:00' }} />
        </MemoryRouter>
      </QueryClientProvider>,
    );
    expect(screen.getByRole('button', { name: 'Tirar do repositório' })).toBeEnabled();
    expect(screen.getByRole('link', { name: 'Ver no repositório' })).toHaveAttribute('href', `/repository/${tcc.id}`);
  });

  it('não aparece para quem não pode agir', () => {
    renderWithProviders(<PublicationCard tcc={makeTcc({ status: 'APPROVED' })} />, '/tcc');

    expect(screen.queryByRole('heading', { name: 'Repositório' })).not.toBeInTheDocument();
  });
});
