/** "Anterior · Página 2 de 5 · Próxima". Some quando há uma página só. */
export function Pager({ page, total, perPage, onChange }: { page: number; total: number; perPage: number; onChange: (page: number) => void }) {
  const pages = Math.max(1, Math.ceil(total / perPage));
  if (pages <= 1) return null;

  return (
    <nav className="btn-row repo-pages" aria-label="Páginas">
      <button type="button" className="secondary" disabled={page <= 1} onClick={() => onChange(page - 1)}>
        Anterior
      </button>
      <span className="muted">
        Página {page} de {pages}
      </span>
      <button type="button" className="secondary" disabled={page >= pages} onClick={() => onChange(page + 1)}>
        Próxima
      </button>
    </nav>
  );
}
