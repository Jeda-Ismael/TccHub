import { Tcc } from '../api/types';

/** TCC de exemplo para os testes de tela; sobrescreva só o que importa. */
export function makeTcc(overrides: Partial<Tcc> = {}): Tcc {
  return {
    id: 't1',
    title: 'Detecção de fraudes',
    abstract: 'Resumo do trabalho',
    status: 'DRAFT',
    student: { id: 's1', name: 'Ana Aluna', email: 'aluno@hubtcc.dev' },
    advisor: { id: 'a1', name: 'Olga Orientadora', email: 'orientador@hubtcc.dev' },
    thematicAreas: [],
    keywords: [],
    document: null,
    documents: [],
    board: [],
    statusHistory: [],
    finalGrade: null,
    publicationAuthorized: false,
    publishedAt: null,
    createdAt: '2026-10-01T10:00:00-03:00',
    updatedAt: '2026-10-01T10:00:00-03:00',
    permissions: { edit: false, upload: false, submit: false, advisorReview: false, evaluate: false, manageBoard: false },
    ...overrides,
  };
}
