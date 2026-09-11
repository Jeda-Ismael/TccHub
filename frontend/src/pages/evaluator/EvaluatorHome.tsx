import { useMemo } from 'react';
import { Link } from 'react-router-dom';
import { useTccs } from '../../api/hooks';
import { StatusBadge } from '../../components/StatusBadge';
import { VerdictBadge } from '../../components/VerdictBadge';
import { useAuth } from '../../auth/AuthContext';

export function EvaluatorHome() {
  const { data: tccs, isLoading } = useTccs();
  const { user } = useAuth();

  const sorted = useMemo(() => {
    if (!tccs) return [];
    // TCCs aguardando meu parecer aparecem primeiro — é a ação pendente do avaliador.
    return [...tccs].sort((a, b) => {
      const aPending = a.status === 'SENT_TO_EVALUATION' && a.evaluators.find((e) => e.evaluatorId === user?.id)?.verdict === 'PENDING';
      const bPending = b.status === 'SENT_TO_EVALUATION' && b.evaluators.find((e) => e.evaluatorId === user?.id)?.verdict === 'PENDING';
      return aPending === bPending ? 0 : aPending ? -1 : 1;
    });
  }, [tccs, user?.id]);

  if (isLoading) return <p>Carregando...</p>;

  return (
    <div className="card">
      <h2>TCCs para avaliar</h2>
      {!tccs?.length && <p className="muted">Você ainda não foi designado para nenhuma banca.</p>}
      {sorted.map((tcc) => {
        const myVerdict = tcc.evaluators.find((e) => e.evaluatorId === user?.id)?.verdict;
        const awaitingMe = tcc.status === 'SENT_TO_EVALUATION' && myVerdict === 'PENDING';
        return (
          <Link key={tcc.id} to={`/evaluator/${tcc.id}`} className="tcc-list-item">
            <div>
              <h3>
                {tcc.title} {awaitingMe && <span className="status-badge status-CORRECTIONS_REQUESTED">Ação pendente</span>}
              </h3>
              <p>{tcc.student.name}</p>
              {myVerdict && (
                <p>
                  Seu parecer: <VerdictBadge verdict={myVerdict} />
                </p>
              )}
            </div>
            <StatusBadge status={tcc.status} />
          </Link>
        );
      })}
    </div>
  );
}
