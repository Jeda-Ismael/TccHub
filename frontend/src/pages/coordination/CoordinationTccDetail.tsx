import { FormEvent, useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { useAssignEvaluators, useTccs, useUpdateTcc, useUsers } from '../../api/hooks';
import { StatusBadge } from '../../components/StatusBadge';
import { TccProgress } from '../../components/TccProgress';
import { DocumentLink } from '../../components/DocumentLink';
import { EvaluatorList } from '../../components/EvaluatorList';
import { BackLink } from '../../components/BackLink';
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

  if (!tcc) return <p className="loading">Carregando...</p>;

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
      <BackLink to="/coordination">Todos os TCCs</BackLink>
      <div className="page-head">
        <div className="page-head-text">
          <h1>{tcc.title}</h1>
        </div>
        <StatusBadge status={tcc.status} />
      </div>

      <TccProgress status={tcc.status} />

      <div className="detail-grid">
        <div>
          <div className="card">
            <dl className="meta-list">
              <div>
                <dt>Aluno</dt>
                <dd>{tcc.student.name}</dd>
              </div>
              <div>
                <dt>Orientador</dt>
                <dd>{tcc.advisor?.name || '—'}</dd>
              </div>
              <div>
                <dt>Documento</dt>
                <dd>
                  {tcc.documentUrl ? (
                    <DocumentLink url={tcc.documentUrl}>Ver PDF (v{tcc.documentVersion})</DocumentLink>
                  ) : (
                    <span className="muted">Nenhum PDF enviado</span>
                  )}
                </dd>
              </div>
              {tcc.projectGrade != null && (
                <div>
                  <dt>Notas</dt>
                  <dd>
                    Projeto {tcc.projectGrade} <span className="muted">·</span> Apresentação {tcc.presentationGrade}
                  </dd>
                </div>
              )}
            </dl>
            <h3>Resumo</h3>
            <p className="abstract">{tcc.abstract}</p>
          </div>

          <div className="card">
            <h3>Banca avaliadora</h3>
            {isTerminal ? (
              <p className="muted">Este TCC já foi concluído, então a banca não pode mais ser alterada.</p>
            ) : (
              <>
                <p className="card-sub">Selecione os avaliadores que farão parte da banca deste TCC.</p>
                <form onSubmit={handleAssignEvaluators}>
                  <div className="option-list">
                    {evaluators?.map((ev) => (
                      <label key={ev.id} className="option-row">
                        <input
                          type="checkbox"
                          checked={selectedEvaluators.includes(ev.id)}
                          onChange={(e) =>
                            setSelectedEvaluators((prev) => (e.target.checked ? [...prev, ev.id] : prev.filter((eid) => eid !== ev.id)))
                          }
                        />
                        <span>
                          {ev.name}
                          <span className="person-email">{ev.email}</span>
                        </span>
                      </label>
                    ))}
                  </div>
                  {!evaluators?.length && <p className="muted">Nenhum usuário com papel de Avaliador cadastrado ainda.</p>}
                  <button type="submit" disabled={assignEvaluators.isPending}>
                    Salvar banca
                  </button>
                </form>
              </>
            )}
          </div>

          {message && (
            <p className="feedback" role="status">
              {message}
            </p>
          )}
        </div>

        <div>
          <div className="card">
            <h3>Publicação</h3>
            <form onSubmit={handlePublishableToggle}>
              <label className="check-row">
                <input type="checkbox" checked={publishable} onChange={(e) => setPublishable(e.target.checked)} />
                Trabalho passível de publicação
              </label>
              <div className="form-actions">
                <button type="submit" className="secondary small" disabled={updateTcc.isPending}>
                  Salvar
                </button>
              </div>
            </form>
          </div>

          {tcc.evaluators.length > 0 && (
            <div className="card">
              <h3>Pareceres da banca</h3>
              <EvaluatorList evaluators={tcc.evaluators} />
            </div>
          )}

          <StatusHistoryTimeline entries={tcc.statusHistory} />
        </div>
      </div>
    </div>
  );
}
