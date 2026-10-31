import { Tcc } from '../api/types';
import { StatusBadge } from './StatusBadge';
import { formatDateTime } from '../utils/dates';

function formatSize(bytes: number) {
  return bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

/** Cabeçalho comum das telas de detalhe: dados do TCC, classificação e PDFs. */
export function TccSummary({ tcc, showStudent = true }: { tcc: Tcc; showStudent?: boolean }) {
  const tags = [...tcc.thematicAreas, ...tcc.keywords];

  return (
    <div className="card">
      <div className="tcc-header">
        <h2>{tcc.title}</h2>
        <StatusBadge status={tcc.status} />
      </div>
      <p className="muted">
        {showStudent && <>Aluno: {tcc.student.name} · </>}
        Orientador: {tcc.advisor?.name ?? 'não definido'}
      </p>
      <p className="tcc-abstract">{tcc.abstract}</p>
      {tags.length > 0 && (
        <div className="role-chips" style={{ marginBottom: '0.75rem' }}>
          {tags.map((tag) => (
            <span key={tag.id} className="role-chip">
              {tag.name}
            </span>
          ))}
        </div>
      )}
      {tcc.document ? (
        <div>
          <a href={tcc.document.downloadUrl} target="_blank" rel="noreferrer">
            Abrir PDF (versão {tcc.document.version})
          </a>
          <span className="muted">
            {' '}
            · {tcc.document.originalName} · {formatSize(tcc.document.sizeBytes)} · enviado em{' '}
            {formatDateTime(tcc.document.uploadedAt)}
          </span>
          {tcc.documents.length > 1 && (
            <details style={{ marginTop: '0.5rem' }}>
              <summary className="muted">Versões anteriores</summary>
              <ul>
                {tcc.documents.slice(1).map((doc) => (
                  <li key={doc.version}>
                    <a href={doc.downloadUrl} target="_blank" rel="noreferrer">
                      Versão {doc.version}
                    </a>
                    <span className="muted"> · {formatDateTime(doc.uploadedAt)}</span>
                  </li>
                ))}
              </ul>
            </details>
          )}
        </div>
      ) : (
        <p className="muted">Nenhum PDF enviado ainda.</p>
      )}
    </div>
  );
}
