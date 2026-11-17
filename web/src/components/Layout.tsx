import { ReactNode, useEffect, useRef, useState } from 'react';
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import { HOME_BY_ROLE } from '../auth/homeByRole';
import { ROLE_LABELS, Role } from '../api/types';
import { NotificationBell } from './NotificationBell';
import { GraduationCapIcon } from './icons/GraduationCapIcon';
import { Avatar } from './Avatar';
import { CalendarIcon, ChevronRightIcon, DocumentIcon, FolderIcon, HomeIcon, LogoutIcon, MenuIcon, SettingsIcon, TagIcon, UsersIcon, XIcon } from './icons';

interface NavItem {
  to: string;
  label: string;
  icon: ReactNode;
  end?: boolean;
  /** Também fica marcado nestas rotas (ex.: detalhe de um TCC) */
  alsoActive?: string;
}

const COORDINATION_ITEMS: NavItem[] = [
  { to: '/coordination', end: true, label: 'TCCs', icon: <DocumentIcon />, alsoActive: '/coordination/tcc/' },
  { to: '/coordination/users', label: 'Usuários', icon: <UsersIcon /> },
  { to: '/coordination/catalogs', label: 'Áreas e Palavras-chave', icon: <TagIcon /> },
  { to: '/coordination/periods', label: 'Prazos', icon: <CalendarIcon /> },
  { to: '/coordination/settings', label: 'Configurações', icon: <SettingsIcon /> },
];

/** Telas estreitas: o menu lateral vira gaveta e o sininho sobe para a barrinha do topo. */
function useIsNarrow() {
  const query = '(max-width: 900px)';
  const get = () => typeof window !== 'undefined' && typeof window.matchMedia === 'function' && window.matchMedia(query).matches;
  const [narrow, setNarrow] = useState(get);
  useEffect(() => {
    if (typeof window.matchMedia !== 'function') return;
    const mq = window.matchMedia(query);
    const onChange = () => setNarrow(mq.matches);
    mq.addEventListener?.('change', onChange);
    return () => mq.removeEventListener?.('change', onChange);
  }, []);
  return narrow;
}

/** Fecha um menu ao clicar fora dele ou apertar Esc. */
function useDismiss(open: boolean, ref: React.RefObject<HTMLElement>, close: () => void) {
  const closeRef = useRef(close);
  closeRef.current = close;
  useEffect(() => {
    if (!open) return;
    function handle(e: MouseEvent | KeyboardEvent) {
      if (e instanceof KeyboardEvent ? e.key === 'Escape' : !ref.current?.contains(e.target as Node)) closeRef.current();
    }
    document.addEventListener('mousedown', handle);
    document.addEventListener('keydown', handle);
    return () => {
      document.removeEventListener('mousedown', handle);
      document.removeEventListener('keydown', handle);
    };
  }, [open, ref]);
}

export function Layout() {
  const { user, activeRole, setActiveRole, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const narrow = useIsNarrow();
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [menuOpen, setMenuOpen] = useState(false);
  const menuRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    setDrawerOpen(false);
    setMenuOpen(false);
  }, [location.pathname]);

  useDismiss(menuOpen, menuRef, () => setMenuOpen(false));

  useEffect(() => {
    if (!drawerOpen) return;
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && setDrawerOpen(false);
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [drawerOpen]);

  function switchRole(role: Role) {
    setActiveRole(role);
    setMenuOpen(false);
    navigate(HOME_BY_ROLE[role]);
  }

  async function handleLogout() {
    await logout();
    navigate('/login', { replace: true });
  }

  const isCoordination = activeRole === 'COORDINATION';
  const mainItems: NavItem[] = [];
  if (user && activeRole && !isCoordination) mainItems.push({ to: HOME_BY_ROLE[activeRole], label: 'Meu painel', icon: <HomeIcon /> });
  if (user) mainItems.push({ to: '/repository', label: 'Repositório', icon: <FolderIcon /> });

  const renderItem = (item: NavItem) => (
    <NavLink
      key={item.to}
      to={item.to}
      end={item.end}
      className={({ isActive }) => (isActive || (item.alsoActive && location.pathname.startsWith(item.alsoActive)) ? 'active' : '')}
    >
      {item.icon}
      <span>{item.label}</span>
    </NavLink>
  );

  const brand = (
    <Link to="/" className="side-brand">
      <span className="brand-mark" aria-hidden="true">
        <GraduationCapIcon size={19} />
      </span>
      hubtcc
    </Link>
  );

  return (
    <div className={`app-shell${drawerOpen ? ' drawer-is-open' : ''}`}>
      <header className="mobile-bar">
        <button type="button" className="mobile-bar-toggle" aria-label="Abrir menu" aria-expanded={drawerOpen} onClick={() => setDrawerOpen(true)}>
          <MenuIcon />
        </button>
        {brand}
        {user && activeRole && narrow && <NotificationBell />}
      </header>

      {drawerOpen && <div className="side-backdrop" onClick={() => setDrawerOpen(false)} aria-hidden="true" />}

      <aside className="side" aria-label="Menu">
        <div className="side-top">
          {brand}
          <button type="button" className="side-close" aria-label="Fechar menu" onClick={() => setDrawerOpen(false)}>
            <XIcon />
          </button>
        </div>

        <nav className="side-nav" aria-label="Navegação principal">
          {isCoordination && (
            <div className="side-group">
              <p className="side-group-title">Coordenação</p>
              {COORDINATION_ITEMS.map(renderItem)}
            </div>
          )}
          <div className="side-group">
            {isCoordination && mainItems.length > 0 && <p className="side-group-title">Consulta</p>}
            {mainItems.map(renderItem)}
          </div>
        </nav>

        {user && activeRole && (
          <div className="side-foot">
            {!narrow && <NotificationBell />}
            <div className="account" ref={menuRef}>
              <button
                type="button"
                className="account-button"
                aria-expanded={menuOpen}
                aria-haspopup="true"
                aria-label={`Conta de ${user.name}`}
                onClick={() => setMenuOpen((open) => !open)}
              >
                <Avatar name={user.name} />
                <span className="account-text">
                  <strong>{user.name}</strong>
                  <span>{ROLE_LABELS[activeRole]}</span>
                </span>
                <ChevronRightIcon size={16} />
              </button>
              {menuOpen && (
                <div className="account-menu-panel">
                  <div className="account-id">
                    <strong>{user.name}</strong>
                    <span>{user.email}</span>
                  </div>
                  {user.roles.length > 1 && (
                    <div className="account-roles">
                      <label htmlFor="role-switcher">Ver como</label>
                      <select id="role-switcher" value={activeRole} onChange={(e) => switchRole(e.target.value as Role)}>
                        {user.roles.map((role) => (
                          <option key={role} value={role}>
                            {ROLE_LABELS[role]}
                          </option>
                        ))}
                      </select>
                    </div>
                  )}
                  <button type="button" className="account-logout" onClick={handleLogout}>
                    <LogoutIcon size={16} />
                    Sair
                  </button>
                </div>
              )}
            </div>
          </div>
        )}
      </aside>

      <main className="container">
        <Outlet />
      </main>
    </div>
  );
}
