import { useTccs } from '../../api/hooks';
import { QueryStatus } from '../../components/QueryStatus';
import { TccListItem } from '../../components/TccListItem';

export function AdvisorHome() {
  const { data: tccs, isLoading, error } = useTccs('ADVISOR');
  const waiting = tccs?.filter((t) => t.permissions.advisorReview) ?? [];
  const others = tccs?.filter((t) => !t.permissions.advisorReview) ?? [];

  return (
    <div>
      <QueryStatus isLoading={isLoading} error={error} />
      {!!waiting.length && (
        <div className="card">
          <h2>Aguardando sua revisão</h2>
          {waiting.map((tcc) => (
            <TccListItem key={tcc.id} tcc={tcc} to={`/advisor/${tcc.id}`} subtitle={tcc.student.name} />
          ))}
        </div>
      )}
      {tccs && (
        <div className="card">
          <h2>Meus orientandos</h2>
          {!tccs.length && <p className="muted">Nenhum TCC sob sua orientação ainda.</p>}
          {others.map((tcc) => (
            <TccListItem key={tcc.id} tcc={tcc} to={`/advisor/${tcc.id}`} subtitle={tcc.student.name} />
          ))}
          {!!tccs.length && !others.length && <p className="muted">Todos os seus orientandos estão aguardando revisão.</p>}
        </div>
      )}
    </div>
  );
}
