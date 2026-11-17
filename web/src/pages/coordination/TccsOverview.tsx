import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useTccPage } from '../../api/hooks';
import { QueryStatus } from '../../components/QueryStatus';
import { Pager } from '../../components/Pager';
import { ChevronRightIcon, SearchIcon } from '../../components/icons';
import { StatusBadge } from '../../components/StatusBadge';
import { STATUS_LABELS, TccStatus } from '../../api/types';
import { useDebouncedValue } from '../../utils/useDebouncedValue';
import { EmptyState } from '../../components/EmptyState';
import { Avatar } from '../../components/Avatar';
import { formatDate } from '../../utils/dates';
import { CoordHead } from './CoordinationLayout';

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
    <div>
      <CoordHead title="TCCs" aside={data && data.total > 0 ? (data.total === 1 ? '1 TCC' : `${data.total} TCCs`) : undefined}>
        Todos os trabalhos do curso. Abra um TCC para montar a banca, acompanhar pareceres e registrar a decisão.
      </CoordHead>
      <section className="card card-flush">
        <div className="toolbar toolbar-pad">
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

        {!tccs.length ? (
          <EmptyState title={filtering ? 'Nenhum TCC corresponde à busca.' : 'Nenhum TCC cadastrado ainda.'}>
            {filtering ? 'Tente outro termo ou limpe o filtro de status.' : 'Os TCCs aparecem aqui quando os alunos se cadastrarem.'}
          </EmptyState>
        ) : (
          <div className="grid-table tcc-grid">
            <div className="grid-table-head" aria-hidden="true">
              <span>Trabalho</span>
              <span>Orientador</span>
              <span>Status</span>
              <span>Atualizado</span>
            </div>
            {tccs.map((tcc) => (
              <Link key={tcc.id} to={`/coordination/tcc/${tcc.id}`} className="grid-table-row">
                <span className="tcc-cell-main">
                  <strong>{tcc.title}</strong>
                  <span>{tcc.student.name}</span>
                </span>
                <span className="tcc-cell-person">
                  {tcc.advisor ? (
                    <>
                      <Avatar name={tcc.advisor.name} />
                      <span>{tcc.advisor.name}</span>
                    </>
                  ) : (
                    <span className="muted">Sem orientador</span>
                  )}
                </span>
                <span>
                  <StatusBadge status={tcc.status} />
                </span>
                <span className="tcc-cell-date">{formatDate(tcc.updatedAt)}</span>
                <span className="chevron" aria-hidden="true">
                  <ChevronRightIcon />
                </span>
              </Link>
            ))}
          </div>
        )}

        {data && (
          <div className="pager-pad">
            <Pager page={data.page} total={data.total} perPage={data.perPage} onChange={setPage} />
          </div>
        )}
      </section>
    </div>
  );
}
