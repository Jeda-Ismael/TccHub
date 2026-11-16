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

  useEffect(() => {
    setSelected(tcc.board.map((m) => m.evaluator.id));
  }, [tcc.id, tcc.updatedAt, tcc.board.length]);

  // O orientador não pode avaliar o próprio orientando
  const candidates = evaluators?.filter((e) => e.id !== tcc.advisor?.id) ?? [];
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
        <div className="checkbox-list stacked">
          {candidates.map((evaluator) => {
            const member = verdictOf(evaluator.id);
            return (
              <label key={evaluator.id}>
                <input
                  type="checkbox"
                  checked={selected.includes(evaluator.id)}
                  onChange={() => setSelected((prev) => (prev.includes(evaluator.id) ? prev.filter((x) => x !== evaluator.id) : [...prev, evaluator.id]))}
                />
                {evaluator.name}
                {member && member.verdict !== 'PENDING' && <span className="muted"> · já deu parecer</span>}
              </label>
            );
          })}
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
