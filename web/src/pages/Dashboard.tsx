import { Navigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import { HOME_BY_ROLE } from '../auth/homeByRole';

export function Dashboard() {
  const { activeRole } = useAuth();
  if (!activeRole) return <Navigate to="/login" replace />;
  return <Navigate to={HOME_BY_ROLE[activeRole]} replace />;
}
