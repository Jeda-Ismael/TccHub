import { Link, NavLink, Outlet, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import { HOME_BY_ROLE } from '../auth/homeByRole';
import { ROLE_LABELS, Role } from '../api/types';
import { NotificationBell } from './NotificationBell';

export function Layout() {
  const { user, activeRole, setActiveRole, logout } = useAuth();
  const navigate = useNavigate();

  function switchRole(role: Role) {
    setActiveRole(role);
    navigate(HOME_BY_ROLE[role]);
  }

  async function handleLogout() {
    await logout();
    navigate('/login', { replace: true });
  }

  return (
    <div className="app-shell">
      <header className="topbar">
        <nav className="topbar-nav">
          <Link to="/" className="topbar-brand">
            hubtcc
          </Link>
          {user && <NavLink to="/repository">Repositório</NavLink>}
        </nav>
        {user && activeRole && (
          <div className="topbar-user">
            <NotificationBell />
            <span className="topbar-name">{user.name}</span>
            {user.roles.length > 1 ? (
              <label className="role-switcher">
                Ver como
                <select value={activeRole} onChange={(e) => switchRole(e.target.value as Role)} aria-label="Ver como">
                  {user.roles.map((role) => (
                    <option key={role} value={role}>
                      {ROLE_LABELS[role]}
                    </option>
                  ))}
                </select>
              </label>
            ) : (
              <span>· {ROLE_LABELS[activeRole]}</span>
            )}
            <button className="secondary" onClick={handleLogout}>
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
