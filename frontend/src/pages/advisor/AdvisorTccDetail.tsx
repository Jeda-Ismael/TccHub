import { FormEvent, useState } from 'react';
import { useParams } from 'react-router-dom';
import { useAdvisorReview, useSetGrades, useTccs } from '../../api/hooks';
import { StatusBadge } from '../../components/StatusBadge';
import { VerdictBadge } from '../../components/VerdictBadge';

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

  if (!tcc) return <p>Carregando...</p>;

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
      <div className="card">
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <h2 style={{ margin: 0 }}>{tcc.title}</h2>
          <StatusBadge status={tcc.status} />
        </div>
        <p className="muted">Aluno: {tcc.student.name} ({tcc.student.email})</p>
        <p>{tcc.abstract}</p>
        {tcc.documentUrl && (
          <p>
            <a href={`${import.meta.env.VITE_API_URL || 'http://localhost:3000'}${tcc.documentUrl}`} target="_blank" rel="noreferrer">
              Ver PDF (v{tcc.documentVersion})
            </a>
          </p>
        )}
        {tcc.evaluators.length > 0 && (
          <div>
            <strong>Banca:</strong>
            <ul>
              {tcc.evaluators.map((e) => (
                <li key={e.id}>
                  {e.evaluator.name} — <VerdictBadge verdict={e.verdict} />
                  {e.comment ? `: ${e.comment}` : ''}
                </li>
              ))}
            </ul>
          </div>
        )}
      </div>

      {canReview && (
        <div className="card">
          <h3>{isFinal ? 'Aprovação final' : 'Revisão da monografia'}</h3>
          <div className="form-group">
            <label>Comentário (opcional)</label>
            <textarea value={comment} onChange={(e) => setComment(e.target.value)} />
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
          <div className="form-group">
            <label>Nota do projeto (0-10)</label>
            <input type="number" min={0} max={10} step={0.1} value={projectGrade} onChange={(e) => setProjectGrade(e.target.value)} required />
          </div>
          <div className="form-group">
            <label>Nota da apresentação (0-10)</label>
            <input
              type="number"
              min={0}
              max={10}
              step={0.1}
              value={presentationGrade}
              onChange={(e) => setPresentationGrade(e.target.value)}
              required
            />
          </div>
          <button type="submit" disabled={setGrades.isPending}>
            Lançar notas
          </button>
        </form>
      </div>

      {message && <p className="muted">{message}</p>}
    </div>
  );
}
