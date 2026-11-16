import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useTccs } from '../../api/hooks';
import { StatusBadge } from '../../components/StatusBadge';
import { STATUS_LABELS, TccStatus } from '../../api/types';
import { ChevronRightIcon, SearchIcon } from '../../components/icons';

export function TccsOverview() {
  const { data: tccs, isLoading } = useTccs();
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<TccStatus | ''>('');

  const filtered = useMemo(() => {
    if (!tccs) return [];
    const term = search.trim().toLowerCase();
    return tccs.filter((tcc) => {
      const matchesSearch =
        !term ||
        tcc.title.toLowerCase().includes(term) ||
        tcc.student.name.toLowerCase().includes(term) ||
        tcc.advisor?.name.toLowerCase().includes(term);
      const matchesStatus = !statusFilter || tcc.status === statusFilter;
      return matchesSearch && matchesStatus;
    });
  }, [tccs, search, statusFilter]);

  if (isLoading) return <p className="loading">Carregando...</p>;

  return (
    <div>
      <div className="page-head">
        <div className="page-head-text">
          <h1>Todos os TCCs</h1>
          <p>Abra um TCC para montar a banca avaliadora ou marcar o trabalho para publicação.</p>
        </div>
      </div>
      <div className="card">
        <div className="toolbar">
          <div className="search">
            <SearchIcon />
            <input
              type="search"
              aria-label="Buscar TCC"
              placeholder="Buscar por título, aluno ou orientador..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>
          <select aria-label="Filtrar por status" value={statusFilter} onChange={(e) => setStatusFilter(e.target.value as TccStatus | '')}>
            <option value="">Todos os status</option>
            {Object.entries(STATUS_LABELS).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
        </div>

        {!tccs?.length && (
          <div className="empty">
            <strong>Nenhum TCC cadastrado ainda</strong>
            Os trabalhos aparecem aqui assim que os alunos fizerem o cadastro.
          </div>
        )}
        {!!tccs?.length && !filtered.length && <div className="empty">Nenhum TCC corresponde à busca.</div>}

        <div className="list">
          {filtered.map((tcc) => (
            <Link key={tcc.id} to={`/coordination/tcc/${tcc.id}`} className="tcc-list-item">
              <div className="tcc-list-item-main">
                <h3>{tcc.title}</h3>
                <p>
                  {tcc.student.name} <span aria-hidden="true">·</span> Orientador: {tcc.advisor?.name || '—'}
                </p>
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
