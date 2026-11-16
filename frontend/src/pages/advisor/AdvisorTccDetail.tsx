import { FormEvent, useState } from 'react';
import { useParams } from 'react-router-dom';
import { useAdvisorReview, useSetGrades, useTccs } from '../../api/hooks';
import { StatusBadge } from '../../components/StatusBadge';
import { TccProgress } from '../../components/TccProgress';
import { DocumentLink } from '../../components/DocumentLink';
import { EvaluatorList } from '../../components/EvaluatorList';
import { BackLink } from '../../components/BackLink';

const REVIEWABLE = ['SUBMITTED', 'RESUBMITTED', 'ADVISOR_FINAL_REVIEW'];

export function AdvisorTccDetail() {
  const { id } = useParams<{ id: string }>();
  const { data: tccs } = useTccs();
  const tcc = tccs?.find((t) => t.id === id);
  const advisorReview = useAdvisorReview(id!);
  const setGrades = useSetGrades(id!);

  const [comment, setComment] = useState('');
  const [projectGrade, setProjectGrade] = useState('');
  const [presentationGrade, setPresentationGrade] = useState('');
  const [message, setMessage] = useState<string | null>(null);

  if (!tcc) return <p className="loading">Carregando...</p>;

  async function handleReview(approve: boolean) {
    setMessage(null);
    try {
      await advisorReview.mutateAsync({ approve, comment: comment || undefined });
      setMessage(approve ? 'Aprovado.' : 'Reprovado.');
      setComment('');
    } catch (err: any) {
      setMessage(err.response?.data?.message || 'Não foi possível registrar a revisão');
    }
  }

  async function handleGrades(e: FormEvent) {
    e.preventDefault();
    setMessage(null);
    try {
      await setGrades.mutateAsync({ projectGrade: Number(projectGrade), presentationGrade: Number(presentationGrade) });
      setMessage('Notas lançadas.');
    } catch (err: any) {
      setMessage(err.response?.data?.message || 'Não foi possível lançar as notas');
    }
  }

  const canReview = REVIEWABLE.includes(tcc.status);
  const isFinal = tcc.status === 'ADVISOR_FINAL_REVIEW';

  return (
    <div>
      <BackLink to="/advisor">Meus orientandos</BackLink>
      <div className="page-head">
        <div className="page-head-text">
          <h1>{tcc.title}</h1>
        </div>
        <StatusBadge status={tcc.status} />
      </div>

      <TccProgress status={tcc.status} />

      <div className="detail-grid">
        <div>
          <div className="card">
            <dl className="meta-list">
              <div>
                <dt>Aluno</dt>
                <dd>
                  {tcc.student.name}
                  <br />
                  <span className="muted">{tcc.student.email}</span>
                </dd>
              </div>
              <div>
                <dt>Documento</dt>
                <dd>
                  {tcc.documentUrl ? (
                    <DocumentLink url={tcc.documentUrl}>Ver PDF (v{tcc.documentVersion})</DocumentLink>
                  ) : (
                    <span className="muted">Nenhum PDF enviado</span>
                  )}
                </dd>
              </div>
            </dl>
            <h3>Resumo</h3>
            <p className="abstract">{tcc.abstract}</p>
          </div>

          {canReview && (
            <div className="card">
              <h3>{isFinal ? 'Aprovação final' : 'Revisão da monografia'}</h3>
              <div className="form-group">
                <label htmlFor="review-comment">Comentário (opcional)</label>
                <textarea id="review-comment" value={comment} onChange={(e) => setComment(e.target.value)} />
              </div>
              <div className="btn-row">
                <button type="button" onClick={() => handleReview(true)} disabled={advisorReview.isPending}>
                  Aprovar
                </button>
                <button type="button" className="danger" onClick={() => handleReview(false)} disabled={advisorReview.isPending}>
                  {isFinal ? 'Solicitar novas correções' : 'Reprovar'}
                </button>
              </div>
            </div>
          )}

          <div className="card">
            <h3>Notas</h3>
            <form onSubmit={handleGrades}>
              <div className="form-row">
                <div className="form-group">
                  <label htmlFor="grade-project">Nota do projeto (0 a 10)</label>
                  <input
                    id="grade-project"
                    type="number"
                    min={0}
                    max={10}
                    step={0.1}
                    value={projectGrade}
                    onChange={(e) => setProjectGrade(e.target.value)}
                    required
                  />
                </div>
                <div className="form-group">
                  <label htmlFor="grade-presentation">Nota da apresentação (0 a 10)</label>
                  <input
                    id="grade-presentation"
                    type="number"
                    min={0}
                    max={10}
                    step={0.1}
                    value={presentationGrade}
                    onChange={(e) => setPresentationGrade(e.target.value)}
                    required
                  />
                </div>
              </div>
              <button type="submit" disabled={setGrades.isPending}>
                Lançar notas
              </button>
            </form>
          </div>

          {message && (
            <p className="feedback" role="status">
              {message}
            </p>
          )}
        </div>

        <div>
          <div className="card">
            <h3>Banca</h3>
            {tcc.evaluators.length > 0 ? (
              <EvaluatorList evaluators={tcc.evaluators} />
            ) : (
              <p className="muted">A banca ainda não foi definida pela coordenação.</p>
            )}
          </div>
          {tcc.projectGrade != null && (
            <div className="card">
              <h3>Notas lançadas</h3>
              <div className="grades">
                <div>
                  <div className="grade-value">{tcc.projectGrade}</div>
                  <div className="grade-label">Projeto</div>
                </div>
                <div>
                  <div className="grade-value">{tcc.presentationGrade}</div>
                  <div className="grade-label">Apresentação</div>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
