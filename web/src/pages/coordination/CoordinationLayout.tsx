import { ReactNode } from 'react';
import { NavLink, Outlet, useLocation } from 'react-router-dom';
import { CalendarIcon, DocumentIcon, SettingsIcon, TagIcon, UsersIcon } from '../../components/icons';

const SECTIONS = [
  { to: '/coordination', end: true, label: 'TCCs', icon: <DocumentIcon /> },
  { to: '/coordination/users', label: 'Usuários', icon: <UsersIcon /> },
  { to: '/coordination/catalogs', label: 'Áreas e Palavras-chave', icon: <TagIcon /> },
  { to: '/coordination/periods', label: 'Prazos', icon: <CalendarIcon /> },
  { to: '/coordination/settings', label: 'Configurações', icon: <SettingsIcon /> },
];

export function CoordinationLayout() {
  // O detalhe de um TCC (/coordination/tcc/:id) continua marcando "TCCs"
  const onTccDetail = useLocation().pathname.startsWith('/coordination/tcc/');
  return (
    <div className="coord">
      <aside className="coord-rail">
        <p className="coord-rail-title" aria-hidden="true">
          Coordenação
        </p>
        <nav className="coord-nav" aria-label="Coordenação">
          {SECTIONS.map((s) => (
            <NavLink key={s.to} to={s.to} end={s.end} className={({ isActive }) => (isActive || (s.end && onTccDetail) ? 'active' : '')}>
              {s.icon}
              <span>{s.label}</span>
            </NavLink>
          ))}
        </nav>
      </aside>
      <div className="coord-main">
        <Outlet />
      </div>
    </div>
  );
}

/** Cabeçalho de cada página da coordenação: título, uma frase do que se faz ali e a ação principal. */
export function CoordHead({ title, children, actions, aside }: { title: string; children?: ReactNode; actions?: ReactNode; aside?: ReactNode }) {
  return (
    <header className="coord-head">
      <div className="coord-head-text">
        <h1>
          {title}
          {aside && <span className="coord-head-count">{aside}</span>}
        </h1>
        {children && <p>{children}</p>}
      </div>
      {actions && <div className="coord-head-actions">{actions}</div>}
    </header>
  );
}
