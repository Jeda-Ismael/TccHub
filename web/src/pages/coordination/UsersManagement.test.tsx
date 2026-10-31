import { describe, expect, it } from 'vitest';
import { toggleRole } from './UsersManagement';

describe('toggleRole', () => {
  it('combina papéis de professor na ordem canônica', () => {
    expect(toggleRole(['COORDINATION'], 'ADVISOR')).toEqual(['ADVISOR', 'COORDINATION']);
  });

  it('marcar Aluno desmarca os demais', () => {
    expect(toggleRole(['ADVISOR', 'EVALUATOR'], 'STUDENT')).toEqual(['STUDENT']);
  });

  it('marcar outro papel desmarca Aluno', () => {
    expect(toggleRole(['STUDENT'], 'EVALUATOR')).toEqual(['EVALUATOR']);
  });

  it('desmarca um papel já marcado', () => {
    expect(toggleRole(['ADVISOR', 'EVALUATOR'], 'ADVISOR')).toEqual(['EVALUATOR']);
  });
});
