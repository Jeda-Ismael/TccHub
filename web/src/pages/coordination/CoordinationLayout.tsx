import { ReactNode } from 'react';
import { Outlet } from 'react-router-dom';

// As seções da coordenação ficam no menu lateral do app (components/Layout.tsx)
export function CoordinationLayout() {
  return (
    <div className="coord">
      <Outlet />
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
