import { ReactNode } from 'react';
import { Outlet, Link, NavLink, useLocation } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import { GraduationCapIcon } from './icons/GraduationCapIcon';
import { CalendarIcon, ClipboardIcon, DocumentIcon, FolderIcon, LogoutIcon, TagIcon, UsersIcon } from './icons';
import { Avatar } from './Avatar';

const ROLE_LABELS: Record<string, string> = {
  STUDENT: 'Aluno',
  ADVISOR: 'Orientador',
  EVALUATOR: 'Avaliador',
  COORDINATION: 'Coordenação',
};

interface NavItem {
  to: string;
  label: string;
  icon: ReactNode;
  end?: boolean;
  // Rotas de detalhe que também devem destacar o item (ex.: /coordination/tcc/:id → "TCCs").
  alsoActive?: string;
}

const NAV_BY_ROLE: Record<string, NavItem[]> = {
  STUDENT: [{ to: '/student', label: 'Meu TCC', icon: <DocumentIcon /> }],
  ADVISOR: [{ to: '/advisor', label: 'Orientandos', icon: <UsersIcon /> }],
  EVALUATOR: [{ to: '/evaluator', label: 'Avaliações', icon: <ClipboardIcon /> }],
  COORDINATION: [
    { to: '/coordination', label: 'TCCs', icon: <FolderIcon />, end: true, alsoActive: '/coordination/tcc/' },
    { to: '/coordination/users', label: 'Usuários', icon: <UsersIcon /> },
    { to: '/coordination/catalogs', label: 'Áreas e palavras-chave', icon: <TagIcon /> },
    { to: '/coordination/periods', label: 'Prazos', icon: <CalendarIcon /> },
  ],
};

export function Layout() {
  const { user, logout } = useAuth();
  const { pathname } = useLocation();
  const nav = user ? NAV_BY_ROLE[user.role] || [] : [];

  return (
    <div className="app-shell">
      <aside className="sidebar">
        <Link to="/" className="sidebar-brand">
          <span className="sidebar-brand-mark">
            <GraduationCapIcon size={20} />
          </span>
          hubtcc
        </Link>

        {user && (
          <nav className="sidebar-nav" aria-label="Navegação principal">
            <span className="sidebar-section">{ROLE_LABELS[user.role]}</span>
            {nav.map((item) => (
              <NavLink
                key={item.to}
                to={item.to}
                end={item.end}
                className={({ isActive }) =>
                  isActive || (item.alsoActive && pathname.startsWith(item.alsoActive)) ? 'active' : ''
                }
              >
                {item.icon}
                {item.label}
              </NavLink>
            ))}
          </nav>
        )}

        {user && (
          <div className="sidebar-user">
            <Avatar name={user.email} />
            <div className="sidebar-user-info">
              <span className="sidebar-user-role">{ROLE_LABELS[user.role]}</span>
              <span className="sidebar-user-email" title={user.email}>
                {user.email}
              </span>
            </div>
            <button className="sidebar-logout" onClick={logout} title="Sair" aria-label="Sair">
              <LogoutIcon />
            </button>
          </div>
        )}
      </aside>
      <main className="container">
        <Outlet />
      </main>
    </div>
  );
}
