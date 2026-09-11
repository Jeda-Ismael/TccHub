import { FormEvent, useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { useAssignEvaluators, useTccs, useUpdateTcc, useUsers } from '../../api/hooks';
import { StatusBadge } from '../../components/StatusBadge';
import { VerdictBadge } from '../../components/VerdictBadge';
import { StatusHistoryTimeline } from '../../components/StatusHistoryTimeline';
import { TccStatus } from '../../api/types';

const TERMINAL_STATUSES: TccStatus[] = ['APPROVED', 'REJECTED'];

export function CoordinationTccDetail() {
  const { id } = useParams<{ id: string }>();
  const { data: tccs } = useTccs();
  const tcc = tccs?.find((t) => t.id === id);
  const { data: evaluators } = useUsers('EVALUATOR');
  const updateTcc = useUpdateTcc(id!);
  const assignEvaluators = useAssignEvaluators(id!);

  const [publishable, setPublishable] = useState(false);
  const [selectedEvaluators, setSelectedEvaluators] = useState<string[]>([]);
  const [message, setMessage] = useState<string | null>(null);

  useEffect(() => {
    if (!tcc) return;
    setPublishable(tcc.publishable);
    setSelectedEvaluators(tcc.evaluators.map((e) => e.evaluatorId));
  }, [tcc?.id, tcc?.updatedAt]);

  if (!tcc) return <p>Carregando...</p>;

  const isTerminal = TERMINAL_STATUSES.includes(tcc.status);

  async function handlePublishableToggle(e: FormEvent) {
    e.preventDefault();
    setMessage(null);
    await updateTcc.mutateAsync({ publishable });
    setMessage('Atualizado.');
  }

  async function handleAssignEvaluators(e: FormEvent) {
    e.preventDefault();
    setMessage(null);
    try {
      await assignEvaluators.mutateAsync(selectedEvaluators);
      setMessage('Banca atualizada.');
    } catch (err: any) {
      setMessage(err.response?.data?.message || 'Não foi possível atualizar a banca');
    }
  }

  return (
    <div>
      <div className="card">
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <h2 style={{ margin: 0 }}>{tcc.title}</h2>
          <StatusBadge status={tcc.status} />
        </div>
        <p className="muted">
          Aluno: {tcc.student.name} · Orientador: {tcc.advisor?.name || '—'}
        </p>
        <p>{tcc.abstract}</p>
        {tcc.documentUrl && (
          <p>
            <a href={`${import.meta.env.VITE_API_URL || 'http://localhost:3000'}${tcc.documentUrl}`} target="_blank" rel="noreferrer">
              Ver PDF (v{tcc.documentVersion})
            </a>
          </p>
        )}
        {tcc.projectGrade != null && (
          <p className="muted">
            Nota do projeto: {tcc.projectGrade} · Nota da apresentação: {tcc.presentationGrade}
          </p>
        )}
      </div>

      <div className="card">
        <h3>Publicação</h3>
        <form onSubmit={handlePublishableToggle}>
          <label style={{ display: 'inline-flex', alignItems: 'center', gap: 6, fontWeight: 400 }}>
            <input type="checkbox" checked={publishable} onChange={(e) => setPublishable(e.target.checked)} />
            Trabalho passível de publicação
          </label>
          <div style={{ marginTop: '0.75rem' }}>
            <button type="submit" disabled={updateTcc.isPending}>
              Salvar
            </button>
          </div>
        </form>
      </div>

      <div className="card">
        <h3>Banca avaliadora</h3>
        {isTerminal ? (
          <p className="muted">Este TCC já foi concluído — a banca não pode mais ser alterada.</p>
        ) : (
          <>
            <p className="muted">Selecione os avaliadores que farão parte da banca deste TCC.</p>
            <form onSubmit={handleAssignEvaluators}>
              {evaluators?.map((ev) => (
                <label key={ev.id} style={{ display: 'block', fontWeight: 400, marginBottom: 4 }}>
                  <input
                    type="checkbox"
                    checked={selectedEvaluators.includes(ev.id)}
                    onChange={(e) =>
                      setSelectedEvaluators((prev) => (e.target.checked ? [...prev, ev.id] : prev.filter((eid) => eid !== ev.id)))
                    }
                  />{' '}
                  {ev.name} ({ev.email})
                </label>
              ))}
              {!evaluators?.length && <p className="muted">Nenhum usuário com papel de Avaliador cadastrado ainda.</p>}
              <button type="submit" disabled={assignEvaluators.isPending} style={{ marginTop: '0.75rem' }}>
                Salvar banca
              </button>
            </form>
          </>
        )}

        {tcc.evaluators.length > 0 && (
          <div style={{ marginTop: '1rem' }}>
            <strong>Pareceres da banca:</strong>
            <ul>
              {tcc.evaluators.map((e) => (
                <li key={e.id}>
                  {e.evaluator.name}: <VerdictBadge verdict={e.verdict} />
                  {e.comment ? ` — ${e.comment}` : ''}
                </li>
              ))}
            </ul>
          </div>
        )}
      </div>

      {message && <p className="muted">{message}</p>}

      <StatusHistoryTimeline entries={tcc.statusHistory} />
    </div>
  );
}
