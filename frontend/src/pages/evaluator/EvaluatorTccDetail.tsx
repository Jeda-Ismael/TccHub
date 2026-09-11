import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { useEvaluate, useTccs } from '../../api/hooks';
import { StatusBadge } from '../../components/StatusBadge';
import { VerdictBadge } from '../../components/VerdictBadge';
import { StatusHistoryTimeline } from '../../components/StatusHistoryTimeline';
import { useAuth } from '../../auth/AuthContext';
import { Tcc, TccEvaluator } from '../../api/types';

// Mensagem contextual do que falta acontecer, já que "não há nada pra fazer
// agora" pode significar situações bem diferentes pro avaliador.
function nextStepMessage(tcc: Tcc, myMembership?: TccEvaluator) {
  if (tcc.status === 'SENT_TO_EVALUATION' && myMembership?.verdict !== 'PENDING') {
    return 'Você já registrou seu parecer. Aguardando o restante da banca para o TCC avançar de status.';
  }
  switch (tcc.status) {
    case 'DRAFT':
    case 'SUBMITTED':
    case 'RESUBMITTED':
      return 'Este TCC ainda não foi encaminhado à banca pelo orientador.';
    case 'CORRECTIONS_REQUESTED':
      return 'A banca solicitou correções ao aluno. Você poderá avaliar novamente assim que o aluno reenviar a monografia e o orientador aprovar o reenvio.';
    case 'ADVISOR_FINAL_REVIEW':
      return 'Todos os avaliadores já se manifestaram. Aguardando a aprovação final do orientador.';
    case 'APPROVED':
      return 'Este TCC já foi aprovado.';
    case 'REJECTED':
      return 'Este TCC foi reprovado pelo orientador.';
    default:
      return 'Este TCC não está aguardando avaliação no momento.';
  }
}

export function EvaluatorTccDetail() {
  const { id } = useParams<{ id: string }>();
  const { data: tccs } = useTccs();
  const tcc = tccs?.find((t) => t.id === id);
  const { user } = useAuth();
  const evaluate = useEvaluate(id!);

  const [comment, setComment] = useState('');
  const [message, setMessage] = useState<string | null>(null);
  const [formError, setFormError] = useState<string | null>(null);

  if (!tcc) return <p>Carregando...</p>;

  const myMembership = tcc.evaluators.find((e) => e.evaluatorId === user?.id);
  const canEvaluate = tcc.status === 'SENT_TO_EVALUATION' && myMembership?.verdict === 'PENDING';

  async function handleVerdict(verdict: 'APPROVED' | 'CORRECTIONS_REQUESTED') {
    setMessage(null);
    setFormError(null);
    if (verdict === 'CORRECTIONS_REQUESTED' && comment.trim().length < 5) {
      setFormError('Explique o que precisa ser corrigido (mínimo 5 caracteres) — o aluno só vê o pedido de correção se houver um comentário.');
      return;
    }
    try {
      await evaluate.mutateAsync({ verdict, comment: comment.trim() || undefined });
      setMessage('Parecer registrado. Aguardando o restante da banca (se houver) para o TCC avançar de status.');
    } catch (err: any) {
      setMessage(err.response?.data?.message || 'Não foi possível registrar o parecer');
    }
  }

  return (
    <div>
      <div className="card">
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <h2 style={{ margin: 0 }}>{tcc.title}</h2>
          <StatusBadge status={tcc.status} />
        </div>
        <p className="muted">
          Aluno: {tcc.student.name} · Orientador: {tcc.advisor?.name || '—'}
        </p>
        {(tcc.thematicAreas.length > 0 || tcc.keywords.length > 0) && (
          <p className="muted">
            {tcc.thematicAreas.map((t) => t.thematicArea.name).join(', ')}
            {tcc.thematicAreas.length > 0 && tcc.keywords.length > 0 ? ' · ' : ''}
            {tcc.keywords.map((k) => k.keyword.name).join(', ')}
          </p>
        )}
        <p>{tcc.abstract}</p>
        {tcc.documentUrl && (
          <p>
            <a href={`${import.meta.env.VITE_API_URL || 'http://localhost:3000'}${tcc.documentUrl}`} target="_blank" rel="noreferrer">
              Baixar PDF (v{tcc.documentVersion})
            </a>
          </p>
        )}
        <div>
          <strong>Parecer da banca:</strong>
          {myMembership?.verdict === 'PENDING' && (
            <p className="muted" style={{ margin: '0.25rem 0' }}>
              Avaliação cega: os pareceres dos demais avaliadores ficam ocultos até você registrar o seu.
            </p>
          )}
          <ul>
            {tcc.evaluators.map((e) => (
              <li key={e.id}>
                {e.evaluator.name}: <VerdictBadge verdict={e.verdict} />
                {e.comment ? ` — ${e.comment}` : ''}
              </li>
            ))}
          </ul>
        </div>
      </div>

      {canEvaluate ? (
        <div className="card">
          <h3>Seu parecer</h3>
          <div className="form-group">
            <label>Comentário</label>
            <textarea value={comment} onChange={(e) => setComment(e.target.value)} placeholder="Obrigatório ao solicitar correção" />
          </div>
          {formError && <p className="error-text">{formError}</p>}
          <div className="btn-row">
            <button type="button" onClick={() => handleVerdict('APPROVED')} disabled={evaluate.isPending}>
              Aprovar
            </button>
            <button type="button" className="danger" onClick={() => handleVerdict('CORRECTIONS_REQUESTED')} disabled={evaluate.isPending}>
              Solicitar correção
            </button>
          </div>
        </div>
      ) : (
        <p className="muted">{nextStepMessage(tcc, myMembership)}</p>
      )}

      {message && <p className="muted">{message}</p>}

      <StatusHistoryTimeline entries={tcc.statusHistory} />
    </div>
  );
}
