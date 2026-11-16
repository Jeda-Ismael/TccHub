import { Outlet } from 'react-router-dom';

// A navegação entre as seções da coordenação (TCCs, Usuários, Áreas, Prazos)
// fica na barra lateral do Layout.
export function CoordinationLayout() {
  return <Outlet />;
}
