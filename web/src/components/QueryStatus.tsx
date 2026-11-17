import { apiErrorMessage } from '../api/client';

/** Carregando / erro de uma consulta, para as telas não ficarem presas em "Carregando...". */
export function QueryStatus({ isLoading, error }: { isLoading: boolean; error: unknown }) {
  if (isLoading) {
    return (
      <div className="skeleton-card" role="status">
        <span className="visually-hidden">Carregando...</span>
        <span className="skeleton-line wide" aria-hidden="true" />
        <span className="skeleton-line" aria-hidden="true" />
        <span className="skeleton-line short" aria-hidden="true" />
      </div>
    );
  }
  if (error) {
    return (
      <div className="notice warning" role="alert">
        <p className="error-text">{apiErrorMessage(error, 'Não foi possível carregar os dados.')}</p>
        <button type="button" className="secondary" onClick={() => window.location.reload()}>
          Tentar de novo
        </button>
      </div>
    );
  }
  return null;
}
