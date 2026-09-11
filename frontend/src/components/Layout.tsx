import { Outlet, Link } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';

const ROLE_LABELS: Record<string, string> = {
  STUDENT: 'Aluno',
  ADVISOR: 'Orientador',
  EVALUATOR: 'Avaliador',
  COORDINATION: 'Coordenação',
};

export function Layout() {
  const { user, logout } = useAuth();

  return (
    <div className="app-shell">
      <header className="topbar">
        <Link to="/" className="topbar-brand">
          hubtcc
        </Link>
        {user && (
          <div className="topbar-user">
            <span>
              {user.email} · {ROLE_LABELS[user.role]}
            </span>
            <button className="secondary" onClick={logout}>
              Sair
            </button>
          </div>
        )}
      </header>
      <main className="container">
        <Outlet />
      </main>
    </div>
  );
}
