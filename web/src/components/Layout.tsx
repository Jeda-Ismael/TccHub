import { useEffect, useRef, useState } from 'react';
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import { HOME_BY_ROLE } from '../auth/homeByRole';
import { ROLE_LABELS, Role } from '../api/types';
import { NotificationBell } from './NotificationBell';
import { GraduationCapIcon } from './icons/GraduationCapIcon';
import { Avatar } from './Avatar';

export function Layout() {
  const { user, activeRole, setActiveRole, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  // No celular o nome e o papel ficam num menu atrás do avatar
  const [menuOpen, setMenuOpen] = useState(false);
  const menuRef = useRef<HTMLDivElement>(null);

  useEffect(() => setMenuOpen(false), [location.pathname]);

  useEffect(() => {
    if (!menuOpen) return;
    function close(e: MouseEvent | KeyboardEvent) {
      if (e instanceof KeyboardEvent ? e.key === 'Escape' : !menuRef.current?.contains(e.target as Node)) setMenuOpen(false);
    }
    document.addEventListener('mousedown', close);
    document.addEventListener('keydown', close);
    return () => {
      document.removeEventListener('mousedown', close);
      document.removeEventListener('keydown', close);
    };
  }, [menuOpen]);

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
        <nav className="topbar-nav" aria-label="Navegação principal">
          <Link to="/" className="topbar-brand">
            <span className="brand-mark" aria-hidden="true">
              <GraduationCapIcon size={19} />
            </span>
            hubtcc
          </Link>
          {user && activeRole && <NavLink to={HOME_BY_ROLE[activeRole]}>Meu painel</NavLink>}
          {user && <NavLink to="/repository">Repositório</NavLink>}
        </nav>
        {user && activeRole && (
          <div className="topbar-user">
            <NotificationBell />
            <span className="topbar-name">
              <Avatar name={user.name} />
              {user.name}
            </span>
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
              <span className="topbar-role">{ROLE_LABELS[activeRole]}</span>
            )}
            <button className="secondary topbar-logout" onClick={handleLogout}>
              Sair
            </button>
            <div className="account-menu" ref={menuRef}>
              <button
                type="button"
                className="account-toggle"
                aria-expanded={menuOpen}
                aria-label={`Conta de ${user.name}`}
                onClick={() => setMenuOpen((open) => !open)}
              >
                <Avatar name={user.name} />
              </button>
              {menuOpen && (
                <div className="account-panel">
                  <strong>{user.name}</strong>
                  <span className="muted">{user.email}</span>
                  <span className="topbar-role-chip">{ROLE_LABELS[activeRole]}</span>
                  <button type="button" className="secondary" onClick={handleLogout}>
                    Sair da conta
                  </button>
                </div>
              )}
            </div>
          </div>
        )}
      </header>
      <main className="container">
        <Outlet />
      </main>
    </div>
  );
}
