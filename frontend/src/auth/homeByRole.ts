import { Role } from '../api/types';

export const HOME_BY_ROLE: Record<Role, string> = {
  STUDENT: '/student',
  ADVISOR: '/advisor',
  EVALUATOR: '/evaluator',
  COORDINATION: '/coordination',
};
