import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from './AuthContext';
import { Role } from '../api/types';

/** Exige login; com `allow`, exige também ter pelo menos um desses papéis. */
export function ProtectedRoute({ allow }: { allow?: Role[] }) {
  const { status, user } = useAuth();
  const location = useLocation();

  if (status === 'loading') return null;
  if (!user) return <Navigate to="/login" replace state={{ from: location.pathname }} />;
  if (allow && !allow.some((role) => user.roles.includes(role))) return <Navigate to="/" replace />;

  return <Outlet />;
}
