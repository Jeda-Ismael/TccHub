import { FormEvent, useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { apiErrorMessage } from '../../api/client';
import { useAssignBoard, useSettings, useTcc, useUsers } from '../../api/hooks';
import { Tcc } from '../../api/types';
import { BoardList } from '../../components/BoardList';
import { DefenseSection } from '../../components/DefenseSection';
import { PublicationCard } from '../../components/PublicationCard';
import { QueryStatus } from '../../components/QueryStatus';
import { StatusHistoryTimeline } from '../../components/StatusHistoryTimeline';
import { TccSummary } from '../../components/TccSummary';
import { toast } from '../../components/Toaster';

export function CoordinationTccDetail() {
  const { id } = useParams<{ id: string }>();
  const { data: tcc, isLoading, error } = useTcc(id);
  if (!tcc) return <QueryStatus isLoading={isLoading} error={error} />;

  return (
    <div>
      <TccSummary tcc={tcc} />
      <PublicationCard tcc={tcc} />
      <DefenseSection tcc={tcc} />
      {tcc.permissions.manageBoard ? <BoardForm tcc={tcc} /> : <BoardList board={tcc.board} />}
      <StatusHistoryTimeline entries={tcc.statusHistory} />
    </div>
  );
}

function BoardForm({ tcc }: { tcc: Tcc }) {
  const { data: evaluators } = useUsers('EVALUATOR');
  const { data: settings } = useSettings();
  const assignBoard = useAssignBoard(tcc.id);
  const [selected, setSelected] = useState<string[]>([]);
  const [message, setMessage] = useState<{ text: string; error: boolean } | null>(null);
  const [filter, setFilter] = useState('');

  useEffect(() => {
    setSelected(tcc.board.map((m) => m.evaluator.id));
  }, [tcc.id, tcc.updatedAt, tcc.board.length]);

  // O orientador não pode avaliar o próprio orientando
  const candidates = evaluators?.filter((e) => e.id !== tcc.advisor?.id) ?? [];
  const term = filter.trim().toLocaleLowerCase('pt-BR');
  // Quem já está escolhido continua visível mesmo fora da busca
  const visible = term
    ? candidates.filter((e) => selected.includes(e.id) || `${e.name} ${e.email}`.toLocaleLowerCase('pt-BR').includes(term))
    : candidates;
  const max = settings?.boardMaxSize;
  const min = settings?.boardMinSize;
  const verdictOf = (evaluatorId: string) => tcc.board.find((m) => m.evaluator.id === evaluatorId);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setMessage(null);
    try {
      await assignBoard.mutateAsync(selected);
      toast('Banca atualizada.');
    } catch (err) {
      setMessage({ text: apiErrorMessage(err, 'Não foi possível atualizar a banca.'), error: true });
    }
  }

  return (
    <div className="card">
      <h3>Banca avaliadora</h3>
      {settings && (
        <p className="muted">
          Escolha de {settings.boardMinSize} a {settings.boardMaxSize} avaliadores. O orientador do TCC não aparece na lista.
          {tcc.status === 'SENT_TO_EVALUATION' && ' Quem continuar na banca mantém o parecer já dado.'}
        </p>
      )}
      <form onSubmit={handleSubmit}>
        {candidates.length > 6 && (
          <div className="field">
            <label htmlFor="board-filter" className="visually-hidden">
              Buscar avaliador
            </label>
            <input
              id="board-filter"
              type="search"
              placeholder="Buscar avaliador por nome ou e-mail"
              value={filter}
              onChange={(e) => setFilter(e.target.value)}
            />
          </div>
        )}
        {max !== undefined && (
          <p className={`board-counter${min !== undefined && (selected.length < min || selected.length > max) ? ' is-off' : ''}`} aria-live="polite">
            {selected.length} de {max} escolhidos
          </p>
        )}
        <div className="checkbox-list stacked">
          {visible.map((evaluator) => {
            const member = verdictOf(evaluator.id);
            return (
              <label key={evaluator.id}>
                <input
                  type="checkbox"
                  checked={selected.includes(evaluator.id)}
                  disabled={max !== undefined && !selected.includes(evaluator.id) && selected.length >= max}
                  onChange={() => setSelected((prev) => (prev.includes(evaluator.id) ? prev.filter((x) => x !== evaluator.id) : [...prev, evaluator.id]))}
                />
                {evaluator.name}
                {member && member.verdict !== 'PENDING' && <span className="muted"> · já deu parecer</span>}
              </label>
            );
          })}
          {candidates.length > 0 && !visible.length && <p className="muted">Nenhum avaliador com esse nome.</p>}
          {!candidates.length && <p className="muted">Nenhum usuário com papel de Avaliador. Cadastre na aba Usuários.</p>}
        </div>
        {message && (
          <p className={message.error ? 'error-text' : 'muted'} role={message.error ? 'alert' : undefined}>
            {message.text}
          </p>
        )}
        <button type="submit" disabled={assignBoard.isPending}>
          Salvar banca ({selected.length})
        </button>
      </form>
    </div>
  );
}
