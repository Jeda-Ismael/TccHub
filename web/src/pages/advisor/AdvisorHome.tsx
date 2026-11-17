import { useTccs } from '../../api/hooks';
import { QueryStatus } from '../../components/QueryStatus';
import { TccListItem } from '../../components/TccListItem';
import { EmptyState } from '../../components/EmptyState';

export function AdvisorHome() {
  const { data: tccs, isLoading, error } = useTccs('ADVISOR');
  // Precisa de mim: revisar, ou agendar uma defesa ainda sem data
  const needsMe = (t: NonNullable<typeof tccs>[number]) => t.permissions.advisorReview || (t.permissions.scheduleDefense && !t.defense?.scheduledAt);
  const waiting = tccs?.filter(needsMe) ?? [];
  const others = tccs?.filter((t) => !needsMe(t)) ?? [];

  return (
    <div>
      <QueryStatus isLoading={isLoading} error={error} />
      {!!waiting.length && (
        <div className="card">
          <h2>Aguardando você</h2>
          {waiting.map((tcc) => (
            <TccListItem key={tcc.id} tcc={tcc} to={`/advisor/${tcc.id}`} subtitle={tcc.student.name} />
          ))}
        </div>
      )}
      {tccs && (
        <div className="card">
          <h2>Meus orientandos</h2>
          {!tccs.length && <EmptyState title="Nenhum TCC sob sua orientação ainda.">Quando um aluno escolher você como orientador, o TCC aparece aqui.</EmptyState>}
          {others.map((tcc) => (
            <TccListItem key={tcc.id} tcc={tcc} to={`/advisor/${tcc.id}`} subtitle={tcc.student.name} />
          ))}
          {!!tccs.length && !others.length && <p className="muted">Todos os seus orientandos estão aguardando revisão.</p>}
        </div>
      )}
    </div>
  );
}
