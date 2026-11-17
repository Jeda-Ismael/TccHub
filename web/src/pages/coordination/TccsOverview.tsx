import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useTccPage } from '../../api/hooks';
import { QueryStatus } from '../../components/QueryStatus';
import { Pager } from '../../components/Pager';
import { ChevronRightIcon, SearchIcon } from '../../components/icons';
import { StatusBadge } from '../../components/StatusBadge';
import { STATUS_LABELS, TccStatus } from '../../api/types';
import { useDebouncedValue } from '../../utils/useDebouncedValue';

export function TccsOverview() {
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<TccStatus | ''>('');
  const [page, setPage] = useState(1);
  const q = useDebouncedValue(search.trim());
  // Busca, filtro e paginação ficam na API: a lista não vem inteira
  const { data, isLoading, error } = useTccPage('COORDINATION', { q, status: statusFilter, page });

  if (isLoading || error) return <QueryStatus isLoading={isLoading} error={error} />;

  const filtering = !!q || !!statusFilter;
  const tccs = data?.items ?? [];

  return (
    <div className="card">
      <div className="tcc-header">
        <h2>Todos os TCCs</h2>
        {data && data.total > 0 && <span className="muted">{data.total === 1 ? '1 TCC' : `${data.total} TCCs`}</span>}
      </div>
      <div className="toolbar">
        <div className="search">
          <SearchIcon />
          <input
            type="search"
            aria-label="Buscar TCC"
            placeholder="Buscar por título, aluno ou orientador..."
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
              setPage(1);
            }}
          />
        </div>
        <select
          value={statusFilter}
          onChange={(e) => {
            setStatusFilter(e.target.value as TccStatus | '');
            setPage(1);
          }}
          aria-label="Filtrar por status"
        >
          <option value="">Todos os status</option>
          {Object.entries(STATUS_LABELS).map(([value, label]) => (
            <option key={value} value={value}>
              {label}
            </option>
          ))}
        </select>
      </div>

      {!tccs.length && <p className="muted">{filtering ? 'Nenhum TCC corresponde à busca.' : 'Nenhum TCC cadastrado ainda.'}</p>}

      {tccs.map((tcc) => (
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

      {data && <Pager page={data.page} total={data.total} perPage={data.perPage} onChange={setPage} />}
    </div>
  );
}
