import { NavLink, Outlet } from 'react-router-dom';

export function CoordinationLayout() {
  return (
    <div>
      <nav className="nav-tabs">
        <NavLink to="/coordination" end className={({ isActive }) => (isActive ? 'active' : '')}>
          TCCs
        </NavLink>
        <NavLink to="/coordination/users" className={({ isActive }) => (isActive ? 'active' : '')}>
          Usuários
        </NavLink>
        <NavLink to="/coordination/catalogs" className={({ isActive }) => (isActive ? 'active' : '')}>
          Áreas e Palavras-chave
        </NavLink>
        <NavLink to="/coordination/periods" className={({ isActive }) => (isActive ? 'active' : '')}>
          Prazos
        </NavLink>
      </nav>
      <Outlet />
    </div>
  );
}
