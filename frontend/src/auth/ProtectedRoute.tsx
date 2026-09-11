import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from './AuthContext';
import { Role } from '../api/types';

export function ProtectedRoute({ allow }: { allow?: Role[] }) {
  const { user } = useAuth();

  if (!user) return <Navigate to="/login" replace />;
  if (allow && !allow.includes(user.role)) return <Navigate to="/" replace />;

  return <Outlet />;
}
