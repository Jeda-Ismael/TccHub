import { Link, useParams } from 'react-router-dom';
import { useRepositoryTcc } from '../../api/hooks';
import { formatGrade } from '../../components/DefenseSection';
import { QueryStatus } from '../../components/QueryStatus';
import { formatSize } from '../../components/TccSummary';
import { formatDate } from '../../utils/dates';

export function RepositoryDetail() {
  const { id } = useParams<{ id: string }>();
  const { data: tcc, isLoading, error } = useRepositoryTcc(id);

  return (
    <div>
      <p>
        <Link to="/repository">← Voltar ao repositório</Link>
      </p>
      {!tcc ? (
        <QueryStatus isLoading={isLoading} error={error} />
      ) : (
        <article className="card">
          <h2>{tcc.title}</h2>
          <p className="muted">
            Autor: {tcc.student.name}
            {tcc.advisor && <> · Orientação: {tcc.advisor.name}</>}
          </p>
          <p className="muted">
            Publicado em {formatDate(tcc.publishedAt)}
            {tcc.finalGrade !== null && <> · Nota final {formatGrade(tcc.finalGrade)}</>}
          </p>
          <h3>Resumo</h3>
          <p className="tcc-abstract">{tcc.abstract}</p>
          {(tcc.thematicAreas.length > 0 || tcc.keywords.length > 0) && (
            <div className="role-chips" style={{ marginBottom: '1rem' }}>
              {[...tcc.thematicAreas, ...tcc.keywords].map((tag) => (
                <span key={tag.id} className="role-chip">
                  {tag.name}
                </span>
              ))}
            </div>
          )}
          {tcc.document && (
            <p>
              <a className="button-link" href={tcc.document.downloadUrl} target="_blank" rel="noreferrer">
                Abrir o PDF
              </a>{' '}
              <span className="muted">
                {tcc.document.originalName} · {formatSize(tcc.document.sizeBytes)}
              </span>
            </p>
          )}
        </article>
      )}
    </div>
  );
}
