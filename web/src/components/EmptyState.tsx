import { ReactNode } from 'react';

/** Lista vazia: diz o que está faltando e, se der, o que fazer. */
export function EmptyState({ title, children }: { title: string; children?: ReactNode }) {
  return (
    <div className="empty">
      <strong>{title}</strong>
      {children && <p>{children}</p>}
    </div>
  );
}
