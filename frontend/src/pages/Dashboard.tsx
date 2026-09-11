import { Navigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';

const HOME_BY_ROLE: Record<string, string> = {
  STUDENT: '/student',
  ADVISOR: '/advisor',
  EVALUATOR: '/evaluator',
  COORDINATION: '/coordination',
};

export function Dashboard() {
  const { user } = useAuth();
  if (!user) return <Navigate to="/login" replace />;
  return <Navigate to={HOME_BY_ROLE[user.role]} replace />;
}
