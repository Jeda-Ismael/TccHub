import { apiErrorMessage } from '../api/client';

/** Carregando / erro de uma consulta, para as telas não ficarem presas em "Carregando...". */
export function QueryStatus({ isLoading, error }: { isLoading: boolean; error: unknown }) {
  if (isLoading) return <p className="muted">Carregando...</p>;
  if (error) {
    return (
      <p className="error-text" role="alert">
        {apiErrorMessage(error, 'Não foi possível carregar os dados.')}
      </p>
    );
  }
  return null;
}
