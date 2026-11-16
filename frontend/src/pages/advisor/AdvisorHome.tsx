import { Link } from 'react-router-dom';
import { useTccs } from '../../api/hooks';
import { StatusBadge } from '../../components/StatusBadge';
import { ChevronRightIcon } from '../../components/icons';

export function AdvisorHome() {
  const { data: tccs, isLoading } = useTccs();

  if (isLoading) return <p className="loading">Carregando...</p>;

  return (
    <div>
      <div className="page-head">
        <div className="page-head-text">
          <h1>Meus orientandos</h1>
          <p>Revise as monografias enviadas e acompanhe cada TCC até a aprovação final.</p>
        </div>
      </div>
      <div className="card">
        {!tccs?.length && (
          <div className="empty">
            <strong>Nenhum orientando ainda</strong>
            Os TCCs aparecem aqui quando um aluno escolhe você como orientador.
          </div>
        )}
        <div className="list">
          {tccs?.map((tcc) => (
            <Link key={tcc.id} to={`/advisor/${tcc.id}`} className="tcc-list-item">
              <div className="tcc-list-item-main">
                <h3>{tcc.title}</h3>
                <p>{tcc.student.name}</p>
              </div>
              <div className="tcc-list-item-side">
                <StatusBadge status={tcc.status} />
                <span className="chevron">
                  <ChevronRightIcon />
                </span>
              </div>
            </Link>
          ))}
        </div>
      </div>
    </div>
  );
}
