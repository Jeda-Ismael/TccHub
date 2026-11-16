import { useMemo } from 'react';
import { Link } from 'react-router-dom';
import { useTccs } from '../../api/hooks';
import { StatusBadge } from '../../components/StatusBadge';
import { VerdictBadge } from '../../components/VerdictBadge';
import { useAuth } from '../../auth/AuthContext';
import { ChevronRightIcon } from '../../components/icons';

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

  if (isLoading) return <p className="loading">Carregando...</p>;

  return (
    <div>
      <div className="page-head">
        <div className="page-head-text">
          <h1>TCCs para avaliar</h1>
          <p>Os trabalhos que aguardam o seu parecer aparecem primeiro.</p>
        </div>
      </div>
      <div className="card">
        {!tccs?.length && (
          <div className="empty">
            <strong>Nenhuma banca por enquanto</strong>
            Você ainda não foi designado para nenhuma banca.
          </div>
        )}
        <div className="list">
          {sorted.map((tcc) => {
            const myVerdict = tcc.evaluators.find((e) => e.evaluatorId === user?.id)?.verdict;
            const awaitingMe = tcc.status === 'SENT_TO_EVALUATION' && myVerdict === 'PENDING';
            return (
              <Link key={tcc.id} to={`/evaluator/${tcc.id}`} className="tcc-list-item">
                <div className="tcc-list-item-main">
                  <h3>
                    {tcc.title} {awaitingMe && <span className="pending-pill">Ação pendente</span>}
                  </h3>
                  <p>
                    {tcc.student.name}
                    {myVerdict && (
                      <>
                        <span aria-hidden="true">·</span> Seu parecer: <VerdictBadge verdict={myVerdict} />
                      </>
                    )}
                  </p>
                </div>
                <div className="tcc-list-item-side">
                  <StatusBadge status={tcc.status} />
                  <span className="chevron">
                    <ChevronRightIcon />
                  </span>
                </div>
              </Link>
            );
          })}
        </div>
      </div>
    </div>
  );
}
