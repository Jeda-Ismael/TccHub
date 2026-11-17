import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useTccs } from '../../api/hooks';
import { QueryStatus } from '../../components/QueryStatus';
import { ChevronRightIcon, SearchIcon } from '../../components/icons';
import { StatusBadge } from '../../components/StatusBadge';
import { STATUS_LABELS, TccStatus } from '../../api/types';

export function TccsOverview() {
  const { data: tccs, isLoading, error } = useTccs('COORDINATION');
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

  if (isLoading || error) return <QueryStatus isLoading={isLoading} error={error} />;

  return (
    <div className="card">
      <h2>Todos os TCCs</h2>
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
        <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value as TccStatus | '')} aria-label="Filtrar por status">
          <option value="">Todos os status</option>
          {Object.entries(STATUS_LABELS).map(([value, label]) => (
            <option key={value} value={value}>
              {label}
            </option>
          ))}
        </select>
      </div>

      {!tccs?.length && <p className="muted">Nenhum TCC cadastrado ainda.</p>}
      {!!tccs?.length && !filtered.length && <p className="muted">Nenhum TCC corresponde à busca.</p>}

      {filtered.map((tcc) => (
        <Link key={tcc.id} to={`/coordination/tcc/${tcc.id}`} className="tcc-list-item">
          <div className="tcc-list-item-main">
            <h3>{tcc.title}</h3>
            <p>
              {tcc.student.name} · Orientador: {tcc.advisor?.name || '—'}
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
  );
}
