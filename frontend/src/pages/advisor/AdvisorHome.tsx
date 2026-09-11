import { Link } from 'react-router-dom';
import { useTccs } from '../../api/hooks';
import { StatusBadge } from '../../components/StatusBadge';

export function AdvisorHome() {
  const { data: tccs, isLoading } = useTccs();

  if (isLoading) return <p>Carregando...</p>;

  return (
    <div className="card">
      <h2>Meus orientandos</h2>
      {!tccs?.length && <p className="muted">Nenhum TCC sob sua orientação ainda.</p>}
      {tccs?.map((tcc) => (
        <Link key={tcc.id} to={`/advisor/${tcc.id}`} className="tcc-list-item">
          <div>
            <h3>{tcc.title}</h3>
            <p>{tcc.student.name}</p>
          </div>
          <StatusBadge status={tcc.status} />
        </Link>
      ))}
    </div>
  );
}
