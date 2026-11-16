import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { useEvaluate, useTccs } from '../../api/hooks';
import { StatusBadge } from '../../components/StatusBadge';
import { TccProgress } from '../../components/TccProgress';
import { DocumentLink } from '../../components/DocumentLink';
import { EvaluatorList } from '../../components/EvaluatorList';
import { BackLink } from '../../components/BackLink';
import { InfoIcon } from '../../components/icons';
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

  if (!tcc) return <p className="loading">Carregando...</p>;

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
      <BackLink to="/evaluator">TCCs para avaliar</BackLink>
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
                <dd>{tcc.student.name}</dd>
              </div>
              <div>
                <dt>Orientador</dt>
                <dd>{tcc.advisor?.name || '—'}</dd>
              </div>
              <div>
                <dt>Documento</dt>
                <dd>
                  {tcc.documentUrl ? (
                    <DocumentLink url={tcc.documentUrl}>Baixar PDF (v{tcc.documentVersion})</DocumentLink>
                  ) : (
                    <span className="muted">Nenhum PDF enviado</span>
                  )}
                </dd>
              </div>
            </dl>
            {(tcc.thematicAreas.length > 0 || tcc.keywords.length > 0) && (
              <div className="tag-list">
                {tcc.thematicAreas.map((t) => (
                  <span key={t.thematicArea.id} className="tag">
                    {t.thematicArea.name}
                  </span>
                ))}
                {tcc.keywords.map((k) => (
                  <span key={k.keyword.id} className="tag">
                    {k.keyword.name}
                  </span>
                ))}
              </div>
            )}
            <h3>Resumo</h3>
            <p className="abstract">{tcc.abstract}</p>
          </div>

          {canEvaluate ? (
            <div className="card">
              <h3>Seu parecer</h3>
              <div className="form-group">
                <label htmlFor="verdict-comment">Comentário</label>
                <textarea
                  id="verdict-comment"
                  value={comment}
                  onChange={(e) => setComment(e.target.value)}
                  placeholder="Obrigatório ao solicitar correção"
                />
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
            <div className="notice info">
              <InfoIcon size={20} />
              <div>{nextStepMessage(tcc, myMembership)}</div>
            </div>
          )}

          {message && (
            <p className="feedback" role="status">
              {message}
            </p>
          )}
        </div>

        <div>
          <div className="card">
            <h3>Parecer da banca</h3>
            {myMembership?.verdict === 'PENDING' && (
              <p className="card-sub">Avaliação cega: os pareceres dos demais avaliadores ficam ocultos até você registrar o seu.</p>
            )}
            <EvaluatorList evaluators={tcc.evaluators} />
          </div>
          <StatusHistoryTimeline entries={tcc.statusHistory} />
        </div>
      </div>
    </div>
  );
}
