import { FormEvent, useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useKeywords, useRepositorySearch, useThematicAreas } from '../../api/hooks';
import { RepositoryFilters, RepositoryTcc } from '../../api/types';
import { QueryStatus } from '../../components/QueryStatus';
import { formatDate } from '../../utils/dates';

/** Filtros guardados na URL: dá para voltar, recarregar e compartilhar a busca. */
function readFilters(params: URLSearchParams): RepositoryFilters {
  const filters: RepositoryFilters = {};
  for (const key of ['q', 'area', 'keyword', 'year'] as const) {
    const value = params.get(key);
    if (value) filters[key] = value;
  }
  const page = Number(params.get('page'));
  if (page > 1) filters.page = page;
  return filters;
}

export function RepositorySearch() {
  const [params, setParams] = useSearchParams();
  const filters = readFilters(params);
  const { data, isLoading, error, isFetching } = useRepositorySearch(filters);
  const { data: areas } = useThematicAreas();
  const { data: keywords } = useKeywords();
  const [text, setText] = useState(filters.q ?? '');

  useEffect(() => setText(filters.q ?? ''), [filters.q]);

  function update(changes: Partial<RepositoryFilters>) {
    const next: Record<string, string> = {};
    const merged = { ...filters, page: undefined, ...changes };
    for (const [key, value] of Object.entries(merged)) {
      if (value !== undefined && value !== '') next[key] = String(value);
    }
    setParams(next);
  }

  function handleSearch(e: FormEvent) {
    e.preventDefault();
    update({ q: text.trim() });
  }

  const hasFilters = Object.keys(filters).some((key) => key !== 'page');
  const pages = data ? Math.max(1, Math.ceil(data.total / data.pageSize)) : 1;
  const page = filters.page ?? 1;
  // Ano da URL que não tem mais TCC (ex.: link antigo): continua aparecendo no select
  const years = data ? [...new Set([...data.years, ...(filters.year ? [Number(filters.year)] : [])])].sort((a, b) => b - a) : [];

  // Página além do fim (ex.: um TCC saiu do repositório): vai para a última
  useEffect(() => {
    if (data && !isFetching && data.total > 0 && page > pages) {
      setParams((current) => {
        const next = new URLSearchParams(current);
        next.set('page', String(pages));
        return next;
      });
    }
  }, [data, isFetching, page, pages, setParams]);

  return (
    <div>
      <div className="card">
        <h2>Repositório de TCCs</h2>
        <p className="muted">Trabalhos aprovados e publicados com autorização dos autores.</p>
        <form onSubmit={handleSearch} className="repo-search" role="search">
          <label htmlFor="repo-q" className="visually-hidden">
            Buscar
          </label>
          <input
            id="repo-q"
            type="search"
            value={text}
            onChange={(e) => setText(e.target.value)}
            placeholder="Busque por título, resumo, palavra-chave, autor..."
            maxLength={200}
          />
          <button type="submit">Buscar</button>
        </form>
        <div className="repo-filters">
          <select aria-label="Área temática" value={filters.area ?? ''} onChange={(e) => update({ area: e.target.value })}>
            <option value="">Todas as áreas</option>
            {areas?.map((a) => (
              <option key={a.id} value={a.id}>
                {a.name}
              </option>
            ))}
          </select>
          <select aria-label="Palavra-chave" value={filters.keyword ?? ''} onChange={(e) => update({ keyword: e.target.value })}>
            <option value="">Todas as palavras-chave</option>
            {keywords?.map((k) => (
              <option key={k.id} value={k.id}>
                {k.name}
              </option>
            ))}
          </select>
          <select aria-label="Ano" value={filters.year ?? ''} onChange={(e) => update({ year: e.target.value })}>
            <option value="">Todos os anos</option>
            {years.map((y) => (
              <option key={y} value={y}>
                {y}
              </option>
            ))}
          </select>
          {hasFilters && (
            <button
              type="button"
              className="link-button"
              onClick={() => {
                setText('');
                setParams({});
              }}
            >
              Limpar filtros
            </button>
          )}
        </div>
      </div>

      {!data && <QueryStatus isLoading={isLoading} error={error} />}
      {data && (
        <>
          <p className="muted" aria-live="polite">
            {data.total === 0
              ? hasFilters
                ? 'Nenhum TCC encontrado com esses filtros.'
                : 'Ainda não há TCCs publicados.'
              : `${data.total} ${data.total === 1 ? 'TCC encontrado' : 'TCCs encontrados'}${isFetching ? '...' : ''}`}
          </p>
          <ul className="repo-list">
            {data.items.map((tcc) => (
              <RepositoryItem key={tcc.id} tcc={tcc} />
            ))}
          </ul>
          {pages > 1 && (
            <nav className="btn-row repo-pages" aria-label="Páginas">
              <button type="button" className="secondary" disabled={page <= 1} onClick={() => update({ ...filters, page: page - 1 })}>
                Anterior
              </button>
              <span className="muted">
                Página {page} de {pages}
              </span>
              <button type="button" className="secondary" disabled={page >= pages} onClick={() => update({ ...filters, page: page + 1 })}>
                Próxima
              </button>
            </nav>
          )}
        </>
      )}
    </div>
  );
}

function RepositoryItem({ tcc }: { tcc: RepositoryTcc }) {
  return (
    <li className="card repo-item">
      <h3>
        <Link to={`/repository/${tcc.id}`}>{tcc.title}</Link>
      </h3>
      <p className="muted">
        {tcc.student.name}
        {tcc.advisor && <> · Orientação: {tcc.advisor.name}</>} · {formatDate(tcc.publishedAt)}
      </p>
      <p className="repo-abstract">{tcc.abstract}</p>
      {(tcc.thematicAreas.length > 0 || tcc.keywords.length > 0) && (
        <div className="role-chips">
          {[...tcc.thematicAreas, ...tcc.keywords].map((tag) => (
            <span key={tag.id} className="role-chip">
              {tag.name}
            </span>
          ))}
        </div>
      )}
    </li>
  );
}
