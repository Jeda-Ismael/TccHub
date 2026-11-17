import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { apiErrorMessage } from '../../api/client';
import { useAdvisorReview, useTcc } from '../../api/hooks';
import { Tcc } from '../../api/types';
import { BoardList } from '../../components/BoardList';
import { DefenseSection } from '../../components/DefenseSection';
import { QueryStatus } from '../../components/QueryStatus';
import { StatusHistoryTimeline } from '../../components/StatusHistoryTimeline';
import { TccSummary } from '../../components/TccSummary';

export function AdvisorTccDetail() {
  const { id } = useParams<{ id: string }>();
  const { data: tcc, isLoading, error } = useTcc(id);
  if (!tcc) return <QueryStatus isLoading={isLoading} error={error} />;

  return (
    <div>
      <TccSummary tcc={tcc} />
      {tcc.permissions.advisorReview && <AdvisorReview tcc={tcc} />}
      <DefenseSection tcc={tcc} />
      <BoardList board={tcc.board} />
      <StatusHistoryTimeline entries={tcc.statusHistory} />
    </div>
  );
}

function AdvisorReview({ tcc }: { tcc: Tcc }) {
  const review = useAdvisorReview(tcc.id);
  const [comment, setComment] = useState('');
  const [error, setError] = useState<string | null>(null);
  const isFinal = tcc.status === 'ADVISOR_FINAL_REVIEW';

  async function handle(approve: boolean) {
    setError(null);
    try {
      await review.mutateAsync({ approve, comment: comment.trim() || undefined });
      setComment('');
    } catch (err) {
      setError(apiErrorMessage(err, 'Não foi possível registrar a revisão.'));
    }
  }

  return (
    <div className="card">
      <h3>{isFinal ? 'Revisão final' : 'Revisão do orientador'}</h3>
      <p className="muted">
        {isFinal
          ? 'A banca aprovou. Aprove para seguir para a defesa, ou devolva ao aluno com o que ainda precisa ser ajustado.'
          : 'Aprove para enviar o TCC à banca (a coordenação precisa ter montado a banca), ou reprove explicando o motivo.'}
      </p>
      <div className="form-group">
        <label htmlFor="review-comment">Comentário {isFinal ? '(obrigatório para devolver)' : '(obrigatório para reprovar)'}</label>
        <textarea id="review-comment" value={comment} onChange={(e) => setComment(e.target.value)} />
      </div>
      {error && (
        <p className="error-text" role="alert">
          {error}
        </p>
      )}
      <div className="btn-row">
        <button type="button" onClick={() => handle(true)} disabled={review.isPending}>
          {isFinal ? 'Aprovar para a defesa' : 'Aprovar e enviar à banca'}
        </button>
        <button type="button" className="danger" onClick={() => handle(false)} disabled={review.isPending}>
          {isFinal ? 'Devolver para correções' : 'Reprovar'}
        </button>
      </div>
    </div>
  );
}
