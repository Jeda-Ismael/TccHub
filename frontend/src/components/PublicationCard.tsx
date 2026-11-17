import { useState } from 'react';
import { Link } from 'react-router-dom';
import { apiErrorMessage } from '../api/client';
import { useAuthorizePublication, usePublish } from '../api/hooks';
import { Tcc } from '../api/types';
import { formatDate } from '../utils/dates';

/**
 * Publicação no repositório (TCC aprovado): o aluno autoriza ou retira; a
 * coordenação publica ou tira do ar. Cada um vê só a ação que pode fazer.
 */
export function PublicationCard({ tcc }: { tcc: Tcc }) {
  const { authorizePublication: canAuthorize, publish: canPublish } = tcc.permissions;
  if (!canAuthorize && !canPublish) return null;

  return (
    <div className="card">
      <h3>Repositório</h3>
      <PublicationStatus tcc={tcc} />
      {canAuthorize && <AuthorizeAction tcc={tcc} />}
      {canPublish && <PublishAction tcc={tcc} />}
    </div>
  );
}

function PublicationStatus({ tcc }: { tcc: Tcc }) {
  if (tcc.publishedAt) {
    return (
      <p>
        <span className="status-badge status-APPROVED">Publicado</span> desde {formatDate(tcc.publishedAt)}.{' '}
        <Link to={`/repository/${tcc.id}`}>Ver no repositório</Link>
      </p>
    );
  }
  if (tcc.publicationAuthorized) {
    return <p className="muted">O aluno autorizou a publicação. Aguardando a coordenação publicar.</p>;
  }
  return <p className="muted">Ainda não autorizado pelo aluno. Sem a autorização, o TCC não aparece no repositório.</p>;
}

function AuthorizeAction({ tcc }: { tcc: Tcc }) {
  const authorize = useAuthorizePublication(tcc.id);
  const [error, setError] = useState<string | null>(null);
  const next = !tcc.publicationAuthorized;

  async function handle() {
    setError(null);
    try {
      await authorize.mutateAsync(next);
    } catch (err) {
      setError(apiErrorMessage(err, 'Não foi possível salvar.'));
    }
  }

  return (
    <div>
      <p className="muted">
        {next
          ? 'Autorize para que seu TCC fique disponível para consulta por alunos e professores logados no sistema.'
          : 'Você pode retirar a autorização a qualquer momento; o TCC sai do repositório na hora.'}
      </p>
      {error && (
        <p className="error-text" role="alert">
          {error}
        </p>
      )}
      <button type="button" className={next ? undefined : 'secondary'} onClick={handle} disabled={authorize.isPending}>
        {next ? 'Autorizar publicação' : 'Retirar autorização'}
      </button>
    </div>
  );
}

function PublishAction({ tcc }: { tcc: Tcc }) {
  const publish = usePublish(tcc.id);
  const [error, setError] = useState<string | null>(null);
  const published = !!tcc.publishedAt;

  async function handle() {
    setError(null);
    try {
      await publish.mutateAsync(!published);
    } catch (err) {
      setError(apiErrorMessage(err, 'Não foi possível salvar.'));
    }
  }

  return (
    <div>
      {error && (
        <p className="error-text" role="alert">
          {error}
        </p>
      )}
      <button
        type="button"
        className={published ? 'danger' : undefined}
        onClick={handle}
        disabled={publish.isPending || (!published && !tcc.publicationAuthorized)}
      >
        {published ? 'Tirar do repositório' : 'Publicar no repositório'}
      </button>
    </div>
  );
}
