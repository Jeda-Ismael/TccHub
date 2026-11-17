import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { apiErrorMessage } from '../../api/client';
import { useEvaluate, useTcc } from '../../api/hooks';
import { Tcc } from '../../api/types';
import { BoardList } from '../../components/BoardList';
import { QueryStatus } from '../../components/QueryStatus';
import { StatusHistoryTimeline } from '../../components/StatusHistoryTimeline';
import { TccSummary } from '../../components/TccSummary';

function nextStep(tcc: Tcc): string | null {
  const me = tcc.board.find((m) => m.isMe);
  if (tcc.status === 'SENT_TO_EVALUATION') {
    if (me?.verdict !== 'PENDING') return 'Seu parecer foi registrado. O TCC avança quando toda a banca terminar.';
    if (!tcc.evaluationPeriodOpen) return 'O prazo de avaliação está fechado. Você poderá dar o parecer quando a coordenação abrir um novo prazo.';
    return 'Leia o PDF e registre seu parecer. Ele não pode ser alterado depois, e os pareceres dos colegas aparecem só depois que você votar.';
  }
  if (tcc.status === 'CORRECTIONS_REQUESTED') return 'A banca pediu correções. Você avalia de novo quando o aluno reenviar e o orientador aprovar.';
  if (['DRAFT', 'SUBMITTED', 'RESUBMITTED'].includes(tcc.status)) return 'O TCC ainda não foi enviado à banca pelo orientador.';
  return null;
}

export function EvaluatorTccDetail() {
  const { id } = useParams<{ id: string }>();
  const { data: tcc, isLoading, error } = useTcc(id);
  if (!tcc) return <QueryStatus isLoading={isLoading} error={error} />;

  const step = nextStep(tcc);

  return (
    <div>
      {step && <p className="notice">{step}</p>}
      <TccSummary tcc={tcc} />
      {tcc.permissions.evaluate && <EvaluationForm tcc={tcc} />}
      <BoardList board={tcc.board} />
      <StatusHistoryTimeline entries={tcc.statusHistory} />
    </div>
  );
}

function EvaluationForm({ tcc }: { tcc: Tcc }) {
  const evaluate = useEvaluate(tcc.id);
  const [comment, setComment] = useState('');
  const [error, setError] = useState<string | null>(null);

  async function handle(verdict: 'APPROVED' | 'CORRECTIONS_REQUESTED') {
    setError(null);
    try {
      await evaluate.mutateAsync({ verdict, comment: comment.trim() || undefined });
    } catch (err) {
      setError(apiErrorMessage(err, 'Não foi possível registrar o parecer.'));
    }
  }

  return (
    <div className="card">
      <h3>Seu parecer</h3>
      <p className="muted">Confira antes de enviar: o parecer não pode ser alterado depois.</p>
      <div className="form-group">
        <label htmlFor="evaluation-comment">Comentário (obrigatório para pedir correções)</label>
        <textarea id="evaluation-comment" value={comment} onChange={(e) => setComment(e.target.value)} />
      </div>
      {error && (
        <p className="error-text" role="alert">
          {error}
        </p>
      )}
      <div className="btn-row">
        <button type="button" onClick={() => handle('APPROVED')} disabled={evaluate.isPending}>
          Aprovar
        </button>
        <button type="button" className="danger" onClick={() => handle('CORRECTIONS_REQUESTED')} disabled={evaluate.isPending}>
          Pedir correções
        </button>
      </div>
    </div>
  );
}
