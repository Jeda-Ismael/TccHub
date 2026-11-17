import { describe, expect, it } from 'vitest';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Route, Routes } from 'react-router-dom';
import { authResponse, mockApi } from '../../test/mockApi';
import { makeTcc, perms } from '../../test/fixtures';
import { renderPage } from '../../test/render';
import { Catalogs } from './Catalogs';
import { CoordinationLayout } from './CoordinationLayout';
import { CoordinationTccDetail } from './CoordinationTccDetail';
import { Periods } from './Periods';
import { Settings } from './Settings';
import { TccsOverview } from './TccsOverview';
import { UsersManagement } from './UsersManagement';

const bodyOf = (calls: ReturnType<typeof mockApi>, method: string, url: string) => {
  const call = calls.find((c) => c.method === method && c.url === url);
  return call ? JSON.parse(call.data) : undefined;
};

describe('coordenação: TCCs', () => {
  it('lista todos e filtra por texto e status', async () => {
    mockApi(() => ({
      status: 200,
      data: [
        makeTcc({ id: 't1', title: 'Detecção de fraudes', status: 'SUBMITTED' }),
        makeTcc({ id: 't2', title: 'Contagem de veículos', status: 'APPROVED', student: { id: 's2', name: 'Bruno', email: 'b' } }),
      ],
    }));
    const user = userEvent.setup();
    renderPage(
      <Routes>
        <Route element={<CoordinationLayout />}>
          <Route path="/coordination" element={<TccsOverview />} />
        </Route>
      </Routes>,
      { path: '/coordination', route: '/*' },
    );

    expect(await screen.findByRole('link', { name: /Detecção de fraudes/ })).toHaveAttribute('href', '/coordination/tcc/t1');
    expect(screen.getByRole('link', { name: 'Prazos' })).toBeInTheDocument();

    await user.type(screen.getByPlaceholderText('Buscar por título, aluno ou orientador...'), 'bruno');
    expect(screen.queryByText('Detecção de fraudes')).not.toBeInTheDocument();
    expect(screen.getByText('Contagem de veículos')).toBeInTheDocument();

    await user.selectOptions(screen.getByRole('combobox'), 'SUBMITTED');
    expect(screen.getByText('Nenhum TCC corresponde à busca.')).toBeInTheDocument();
  });

  it('sem TCCs', async () => {
    mockApi(() => ({ status: 200, data: [] }));

    renderPage(<TccsOverview />);

    expect(await screen.findByText('Nenhum TCC cadastrado ainda.')).toBeInTheDocument();
  });

  it('monta a banca sem o orientador na lista', async () => {
    const tcc = makeTcc({
      status: 'SUBMITTED',
      permissions: perms({ manageBoard: true }),
      board: [{ id: 'b1', evaluator: { id: 'e1', name: 'Arthur', email: 'x' }, verdict: 'PENDING', comment: null, isMe: false }],
    });
    const calls = mockApi((config) => {
      if (config.url === '/users') {
        return {
          status: 200,
          data: [
            { id: 'e1', name: 'Arthur', email: 'x', roles: ['EVALUATOR'] },
            { id: 'e2', name: 'Alice', email: 'y', roles: ['EVALUATOR'] },
            { id: 'a1', name: 'Olga Orientadora', email: 'z', roles: ['ADVISOR', 'EVALUATOR'] },
          ],
        };
      }
      if (config.url === '/settings') return { status: 200, data: { boardMinSize: 2, boardMaxSize: 3, passingGrade: 6 } };
      return { status: 200, data: tcc };
    });
    const user = userEvent.setup();
    renderPage(<CoordinationTccDetail />, { path: `/coordination/tcc/${tcc.id}`, route: '/coordination/tcc/:id' });

    expect(await screen.findByText(/Escolha de 2 a 3 avaliadores/)).toBeInTheDocument();
    const board = screen.getByRole('heading', { name: 'Banca avaliadora' }).parentElement!;
    expect(within(board).queryByText('Olga Orientadora')).not.toBeInTheDocument();
    expect(within(board).getByLabelText('Arthur')).toBeChecked();

    await user.click(within(board).getByLabelText('Alice'));
    await user.click(screen.getByRole('button', { name: 'Salvar banca (2)' }));

    await waitFor(() => expect(bodyOf(calls, 'put', `/tcc/${tcc.id}/evaluators`)).toEqual({ evaluatorIds: ['e1', 'e2'] }));
    expect(await screen.findByText('Banca atualizada.')).toBeInTheDocument();
  });

  it('erro ao salvar a banca', async () => {
    const tcc = makeTcc({ permissions: perms({ manageBoard: true }) });
    mockApi((config) => {
      if (config.method === 'put') return { status: 422, data: { statusCode: 422, message: 'A banca pode ter no máximo 3 avaliadores.' } };
      if (config.url === '/users') return { status: 200, data: [] };
      if (config.url === '/settings') return { status: 200, data: { boardMinSize: 2, boardMaxSize: 3, passingGrade: 6 } };
      return { status: 200, data: tcc };
    });
    const user = userEvent.setup();
    renderPage(<CoordinationTccDetail />, { path: `/coordination/tcc/${tcc.id}`, route: '/coordination/tcc/:id' });

    expect(await screen.findByText(/Nenhum usuário com papel de Avaliador/)).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Salvar banca (0)' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('A banca pode ter no máximo 3 avaliadores.');
  });
});

describe('coordenação: configurações', () => {
  it('carrega e salva', async () => {
    const calls = mockApi((config) => ({ status: 200, data: config.method === 'put' ? JSON.parse(config.data) : { boardMinSize: 2, boardMaxSize: 3, passingGrade: 6 } }));
    const user = userEvent.setup();
    renderPage(<Settings />);

    const passing = await screen.findByLabelText('Nota mínima para aprovação na defesa');
    expect(passing).toHaveValue(6);
    await user.clear(passing);
    await user.type(passing, '7');
    await user.click(screen.getByRole('button', { name: 'Salvar' }));

    expect(await screen.findByText('Configurações salvas.')).toBeInTheDocument();
    expect(bodyOf(calls, 'put', '/settings')).toEqual({ boardMinSize: 2, boardMaxSize: 3, passingGrade: 7 });
  });

  it('mostra o erro da API', async () => {
    mockApi((config) =>
      config.method === 'put'
        ? { status: 422, data: { statusCode: 422, message: 'O mínimo não pode ser maior que o máximo.' } }
        : { status: 200, data: { boardMinSize: 2, boardMaxSize: 3, passingGrade: 6 } },
    );
    const user = userEvent.setup();
    renderPage(<Settings />);

    await user.click(await screen.findByRole('button', { name: 'Salvar' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('O mínimo não pode ser maior que o máximo.');
  });
});

describe('coordenação: prazos', () => {
  const period = { id: 'p1', type: 'SUBMISSION', label: '2026/2', startDate: '2026-08-01T00:00:00-03:00', endDate: '2026-12-15T23:59:59-03:00', active: true };

  it('lista, cria, edita e exclui', async () => {
    const calls = mockApi((config) => ({ status: 200, data: config.method === 'get' ? [period] : period }));
    const user = userEvent.setup();
    renderPage(<Periods />);

    expect(await screen.findByText(/Submissão — 2026\/2/)).toBeInTheDocument();
    expect(screen.getByText('Ativo agora')).toBeInTheDocument();
    expect(screen.getByText('01/08/2026 até 15/12/2026')).toBeInTheDocument();

    await user.selectOptions(screen.getByLabelText('Tipo'), 'EVALUATION');
    await user.type(screen.getByLabelText('Rótulo (ex: 2026/1)'), '2027/1');
    await user.type(screen.getByLabelText('Início'), '2027-02-01');
    await user.type(screen.getByLabelText('Fim'), '2027-06-30');
    await user.click(screen.getByRole('button', { name: 'Criar prazo' }));
    await waitFor(() => expect(bodyOf(calls, 'post', '/periods')).toEqual({ type: 'EVALUATION', label: '2027/1', startDate: '2027-02-01', endDate: '2027-06-30' }));

    await user.click(screen.getByRole('button', { name: 'Editar' }));
    expect(screen.getByRole('heading', { name: 'Editar prazo' })).toBeInTheDocument();
    expect(screen.getByLabelText('Início')).toHaveValue('2026-08-01');
    await user.click(screen.getByRole('button', { name: 'Salvar alterações' }));
    await waitFor(() => expect(calls.some((c) => c.method === 'put' && c.url === '/periods/p1')).toBe(true));

    await user.click(screen.getByRole('button', { name: 'Excluir' }));
    await user.click(screen.getByRole('button', { name: 'Confirmar' }));
    await waitFor(() => expect(calls.some((c) => c.method === 'delete' && c.url === '/periods/p1')).toBe(true));
  });

  it('cancela a edição e mostra erro ao salvar', async () => {
    mockApi((config) =>
      config.method === 'get' ? { status: 200, data: [period] } : { status: 422, data: { statusCode: 422, message: 'O fim deve ser depois do início.' } },
    );
    const user = userEvent.setup();
    renderPage(<Periods />);

    await user.click(await screen.findByRole('button', { name: 'Editar' }));
    await user.click(screen.getByRole('button', { name: 'Cancelar edição' }));
    expect(screen.getByRole('heading', { name: 'Novo prazo' })).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Excluir' }));
    await user.click(screen.getByRole('button', { name: 'Cancelar' }));

    await user.type(screen.getByLabelText('Rótulo (ex: 2026/1)'), 'x');
    await user.type(screen.getByLabelText('Início'), '2027-02-01');
    await user.type(screen.getByLabelText('Fim'), '2027-01-01');
    await user.click(screen.getByRole('button', { name: 'Criar prazo' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('O fim deve ser depois do início.');
  });

  it('sem prazos', async () => {
    mockApi(() => ({ status: 200, data: [] }));

    renderPage(<Periods />);

    expect(await screen.findByText('Nenhum prazo cadastrado.')).toBeInTheDocument();
  });
});

describe('coordenação: áreas e palavras-chave', () => {
  it('cria, renomeia e exclui; item em uso não pode ser excluído', async () => {
    const calls = mockApi((config) => {
      if (config.url === '/thematic-areas' && config.method === 'get') {
        return { status: 200, data: [{ id: 'a1', name: 'Redes', tccCount: 0 }, { id: 'a2', name: 'IA', tccCount: 3 }] };
      }
      if (config.url === '/keywords' && config.method === 'get') return { status: 200, data: [] };
      return { status: 200, data: { id: 'n', name: 'x', tccCount: 0 } };
    });
    const user = userEvent.setup();
    renderPage(<Catalogs />);

    expect(await screen.findByText(/usado em 3 TCC\(s\)/)).toBeInTheDocument();
    expect(screen.getByText('Nenhuma palavra-chave cadastrada.')).toBeInTheDocument();
    const areas = screen.getByRole('heading', { name: 'Áreas temáticas' }).parentElement!;
    const deleteButtons = within(areas).getAllByRole('button', { name: 'Excluir' });
    expect(deleteButtons[1]).toBeDisabled();

    await user.type(within(areas).getByPlaceholderText('Nova área temática'), 'Banco de Dados');
    await user.click(within(areas).getByRole('button', { name: 'Adicionar' }));
    await waitFor(() => expect(bodyOf(calls, 'post', '/thematic-areas')).toEqual({ name: 'Banco de Dados' }));

    await user.click(within(areas).getAllByRole('button', { name: 'Editar' })[0]);
    const input = within(areas).getByDisplayValue('Redes');
    await user.clear(input);
    await user.type(input, 'Redes de Computadores');
    await user.click(within(areas).getByRole('button', { name: 'Salvar' }));
    await waitFor(() => expect(bodyOf(calls, 'put', '/thematic-areas/a1')).toEqual({ name: 'Redes de Computadores' }));

    await user.click(deleteButtons[0]);
    expect(within(areas).getByText('Excluir "Redes"?')).toBeInTheDocument();
    await user.click(within(areas).getByRole('button', { name: 'Confirmar' }));
    await waitFor(() => expect(calls.some((c) => c.method === 'delete' && c.url === '/thematic-areas/a1')).toBe(true));
  });

  it('palavras-chave: erro ao criar e cancelar edição', async () => {
    mockApi((config) => {
      if (config.url === '/keywords' && config.method === 'get') return { status: 200, data: [{ id: 'k1', name: 'nuvem', tccCount: 0 }] };
      if (config.url === '/thematic-areas') return { status: 200, data: [] };
      return { status: 409, data: { statusCode: 409, message: 'Já existe uma palavra-chave com este nome.' } };
    });
    const user = userEvent.setup();
    renderPage(<Catalogs />);

    const keywords = (await screen.findByRole('heading', { name: 'Palavras-chave' })).parentElement!;
    await within(keywords).findByText('nuvem');
    await user.type(within(keywords).getByPlaceholderText('Nova palavra-chave'), 'nuvem');
    await user.click(within(keywords).getByRole('button', { name: 'Adicionar' }));
    expect(await within(keywords).findByText('Já existe uma palavra-chave com este nome.')).toBeInTheDocument();

    await user.clear(within(keywords).getByPlaceholderText('Nova palavra-chave'));
    await user.click(within(keywords).getByRole('button', { name: 'Editar' }));
    const input = within(keywords).getByDisplayValue('nuvem');
    await user.type(input, ' privada');
    await user.click(within(keywords).getByRole('button', { name: 'Salvar' }));
    // Erro do cadastro e erro da edição, cada um no seu lugar
    await waitFor(() => expect(within(keywords).getAllByText('Já existe uma palavra-chave com este nome.')).toHaveLength(2));
    await user.click(within(keywords).getByRole('button', { name: 'Cancelar' }));
    expect(within(keywords).getByText('nuvem')).toBeInTheDocument();

    await user.click(within(keywords).getByRole('button', { name: 'Excluir' }));
    await user.click(within(keywords).getByRole('button', { name: 'Cancelar' }));
    expect(within(keywords).getByText('nuvem')).toBeInTheDocument();
  });

  it('erro ao excluir aparece no card', async () => {
    mockApi((config) => {
      if (config.url === '/keywords' && config.method === 'get') return { status: 200, data: [{ id: 'k1', name: 'nuvem', tccCount: 0 }] };
      if (config.url === '/thematic-areas') return { status: 200, data: [] };
      return { status: 409, data: { statusCode: 409, message: 'Não é possível excluir: esta palavra-chave está em uso por 1 TCC(s).' } };
    });
    const user = userEvent.setup();
    renderPage(<Catalogs />);

    const keywords = (await screen.findByRole('heading', { name: 'Palavras-chave' })).parentElement!;
    await user.click(await within(keywords).findByRole('button', { name: 'Excluir' }));
    await user.click(within(keywords).getByRole('button', { name: 'Confirmar' }));

    expect(await within(keywords).findByText('Não é possível excluir: esta palavra-chave está em uso por 1 TCC(s).')).toBeInTheDocument();
  });
});

describe('coordenação: usuários', () => {
  const users = [
    { id: 'u1', name: 'Paulo Professor', email: 'paulo@hubtcc.dev', roles: ['COORDINATION'], createdAt: '2026-10-01T00:00:00+00:00' },
    { id: 'u2', name: 'Ana Aluna', email: 'ana@hubtcc.dev', roles: ['STUDENT'], createdAt: '2026-10-01T00:00:00+00:00' },
    { id: 'u3', name: 'Olga Orientadora', email: 'olga@hubtcc.dev', roles: ['ADVISOR'], createdAt: '2026-10-01T00:00:00+00:00' },
  ];

  function mockUsers(failWrites = false) {
    return mockApi((config) => {
      if (config.url === '/auth/refresh') return { status: 200, data: authResponse(['COORDINATION']) };
      if (config.method === 'get') return { status: 200, data: users };
      if (!failWrites) return { status: 200, data: users[1] };
      const messages: Record<string, string> = {
        put: 'Aluno não combina com outros papéis.',
        delete: 'Este usuário tem TCCs e não pode ser excluído.',
        post: 'Já existe um usuário com este e-mail.',
      };
      const message = messages[config.method ?? ''];
      return { status: 422, data: { statusCode: 422, message } };
    });
  }

  it('filtra, edita papéis, exclui e cria', async () => {
    const calls = mockUsers();
    const user = userEvent.setup();
    renderPage(<UsersManagement />, { auth: true });

    expect(await screen.findByText('Ana Aluna')).toBeInTheDocument();
    // Não oferece excluir a própria conta (u1 é o usuário logado no mock)
    expect(screen.getAllByRole('button', { name: 'Excluir' })).toHaveLength(2);

    await user.selectOptions(screen.getByLabelText('Filtrar por papel'), 'ADVISOR');
    expect(screen.queryByText('Ana Aluna')).not.toBeInTheDocument();
    await user.selectOptions(screen.getByLabelText('Filtrar por papel'), '');
    await user.type(screen.getByPlaceholderText('Buscar por nome ou e-mail...'), 'zzz');
    expect(screen.getByText('Nenhum usuário corresponde à busca.')).toBeInTheDocument();
    await user.clear(screen.getByPlaceholderText('Buscar por nome ou e-mail...'));

    const olga = screen.getByText('Olga Orientadora').closest('tr')!;
    await user.click(within(olga).getByRole('button', { name: 'Editar' }));
    await user.click(within(olga).getByLabelText('Avaliador'));
    await user.click(within(olga).getByRole('button', { name: 'Salvar' }));
    await waitFor(() => expect(bodyOf(calls, 'put', '/users/u3/roles')).toEqual({ roles: ['ADVISOR', 'EVALUATOR'] }));

    const ana = screen.getByText('Ana Aluna').closest('tr')!;
    await user.click(within(ana).getByRole('button', { name: 'Excluir' }));
    await user.click(within(ana).getByRole('button', { name: 'Confirmar' }));
    await waitFor(() => expect(calls.some((c) => c.method === 'delete' && c.url === '/users/u2')).toBe(true));

    await user.type(screen.getByLabelText('Nome'), 'Novo Professor');
    await user.type(screen.getByLabelText('E-mail'), 'novo@hubtcc.dev');
    await user.type(screen.getByLabelText('Senha inicial'), 'senha-forte');
    await user.click(screen.getByRole('button', { name: 'Criar usuário' }));
    await waitFor(() =>
      expect(bodyOf(calls, 'post', '/users')).toEqual({ name: 'Novo Professor', email: 'novo@hubtcc.dev', password: 'senha-forte', roles: ['ADVISOR'] }),
    );
  });

  it('mostra os erros da API', async () => {
    mockUsers(true);
    const user = userEvent.setup();
    renderPage(<UsersManagement />, { auth: true });

    const ana = (await screen.findByText('Ana Aluna')).closest('tr')!;
    await user.click(within(ana).getByRole('button', { name: 'Editar' }));
    await user.click(within(ana).getByRole('button', { name: 'Cancelar' }));
    await user.click(within(ana).getByRole('button', { name: 'Editar' }));
    await user.click(within(ana).getByRole('button', { name: 'Salvar' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('Aluno não combina com outros papéis.');
    await user.click(within(ana).getByRole('button', { name: 'Cancelar' }));

    await user.click(within(ana).getByRole('button', { name: 'Excluir' }));
    await user.click(within(ana).getByRole('button', { name: 'Cancelar' }));
    await user.click(within(ana).getByRole('button', { name: 'Excluir' }));
    await user.click(within(ana).getByRole('button', { name: 'Confirmar' }));
    expect(await screen.findByText('Este usuário tem TCCs e não pode ser excluído.')).toHaveAttribute('role', 'alert');

    await user.type(screen.getByLabelText('Nome'), 'X');
    await user.type(screen.getByLabelText('E-mail'), 'x@hubtcc.dev');
    await user.type(screen.getByLabelText('Senha inicial'), 'senha-forte');
    await user.click(screen.getByRole('button', { name: 'Criar usuário' }));
    expect(await screen.findByText('Já existe um usuário com este e-mail.')).toHaveAttribute('role', 'alert');
  });
});
