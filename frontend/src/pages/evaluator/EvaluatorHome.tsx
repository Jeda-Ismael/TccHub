import { useTccs } from '../../api/hooks';
import { QueryStatus } from '../../components/QueryStatus';
import { TccListItem } from '../../components/TccListItem';

export function EvaluatorHome() {
  const { data: tccs, isLoading, error } = useTccs('EVALUATOR');
  // Aguardando o meu parecer: posso avaliar e ainda não votei
  const isWaitingMe = (t: NonNullable<typeof tccs>[number]) => t.permissions.evaluate && t.board.some((m) => m.isMe && m.verdict === 'PENDING');
  const waiting = tccs?.filter(isWaitingMe) ?? [];
  const others = tccs?.filter((t) => !isWaitingMe(t)) ?? [];

  return (
    <div>
      <QueryStatus isLoading={isLoading} error={error} />
      {!!waiting.length && (
        <div className="card">
          <h2>Aguardando seu parecer</h2>
          {waiting.map((tcc) => (
            <TccListItem key={tcc.id} tcc={tcc} to={`/evaluator/${tcc.id}`} subtitle={`${tcc.student.name} · Orientador: ${tcc.advisor?.name ?? '—'}`} />
          ))}
        </div>
      )}
      {tccs && (
        <div className="card">
          <h2>Minhas bancas</h2>
          {!tccs.length && <p className="muted">Você ainda não foi incluído em nenhuma banca.</p>}
          {others.map((tcc) => (
            <TccListItem key={tcc.id} tcc={tcc} to={`/evaluator/${tcc.id}`} subtitle={`${tcc.student.name} · Orientador: ${tcc.advisor?.name ?? '—'}`} />
          ))}
        </div>
      )}
    </div>
  );
}
