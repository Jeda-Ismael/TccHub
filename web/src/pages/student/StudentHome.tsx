import { FormEvent, useEffect, useState } from 'react';
import { apiErrorMessage } from '../../api/client';
import { TccInput, useCreateTcc, useKeywords, useSubmitTcc, useTcc, useTccs, useThematicAreas, useUpdateTcc, useUploadDocument, useUsers } from '../../api/hooks';
import { Tcc, TccStatus } from '../../api/types';
import { BoardList } from '../../components/BoardList';
import { DefenseSection } from '../../components/DefenseSection';
import { PublicationCard } from '../../components/PublicationCard';
import { QueryStatus } from '../../components/QueryStatus';
import { StatusHistoryTimeline } from '../../components/StatusHistoryTimeline';
import { TccSummary } from '../../components/TccSummary';

/** O que o aluno precisa saber/fazer em cada status. */
const NEXT_STEP: Record<TccStatus, string> = {
  DRAFT: 'Preencha os dados, escolha o orientador, envie o PDF e clique em "Submeter TCC".',
  SUBMITTED: 'Seu TCC está com o orientador para a primeira revisão.',
  RESUBMITTED: 'Sua nova versão está com o orientador.',
  SENT_TO_EVALUATION: 'A banca está avaliando seu TCC. Os pareceres aparecem quando todos terminarem.',
  CORRECTIONS_REQUESTED: 'Faça as correções pedidas, envie a nova versão do PDF e clique em "Reenviar TCC".',
  ADVISOR_FINAL_REVIEW: 'A banca aprovou! Falta a revisão final do orientador.',
  AWAITING_DEFENSE: 'Aprovado para a defesa! Veja abaixo a data e o local quando forem agendados.',
  AWAITING_DECISION: 'A coordenação está decidindo os próximos passos da sua defesa.',
  APPROVED: 'Parabéns, seu TCC foi aprovado!',
  REJECTED: 'Seu TCC foi reprovado. Veja os comentários no histórico.',
};

export function StudentHome() {
  const { data: tccs, isLoading, error } = useTccs('STUDENT');
  const [creatingNew, setCreatingNew] = useState(false);

  if (isLoading || error) return <QueryStatus isLoading={isLoading} error={error} />;

  const latest = tccs?.[0];
  if (!latest || creatingNew) return <TccForm onCreated={() => setCreatingNew(false)} />;

  return <StudentTcc tccId={latest.id} onStartNew={latest.status === 'REJECTED' ? () => setCreatingNew(true) : undefined} />;
}

function StudentTcc({ tccId, onStartNew }: { tccId: string; onStartNew?: () => void }) {
  const { data: tcc, isLoading, error } = useTcc(tccId);
  if (!tcc) return <QueryStatus isLoading={isLoading} error={error} />;

  const corrections = tcc.board.filter((m) => m.verdict === 'CORRECTIONS_REQUESTED' && m.comment);
  const lastComment = [...tcc.statusHistory].reverse().find((h) => h.comment && h.changedBy && h.toStatus === tcc.status);
  const roundFinished = tcc.board.some((m) => m.verdict !== 'PENDING');

  return (
    <div>
      <p className={`notice${tcc.status === 'CORRECTIONS_REQUESTED' || tcc.status === 'REJECTED' ? ' warning' : ''}`}>{NEXT_STEP[tcc.status]}</p>

      <TccSummary tcc={tcc} showStudent={false} />

      {tcc.status === 'CORRECTIONS_REQUESTED' && (corrections.length > 0 || lastComment) && (
        <div className="card">
          <h3>Correções pedidas</h3>
          <ul>
            {corrections.map((c) => (
              <li key={c.id}>
                <strong>{c.evaluator.name}:</strong> {c.comment}
              </li>
            ))}
            {lastComment && corrections.length === 0 && (
              <li>
                <strong>{lastComment.changedBy?.name}:</strong> {lastComment.comment}
              </li>
            )}
          </ul>
        </div>
      )}

      {tcc.permissions.edit && <TccForm tcc={tcc} />}
      {(tcc.permissions.upload || tcc.permissions.submit) && <UploadAndSubmit tcc={tcc} />}
      <PublicationCard tcc={tcc} />
      <DefenseSection tcc={tcc} />
      {roundFinished && <BoardList board={tcc.board} title="Pareceres da banca" />}
      <StatusHistoryTimeline entries={tcc.statusHistory} />

      {onStartNew && (
        <button type="button" onClick={onStartNew}>
          Cadastrar um novo TCC
        </button>
      )}
    </div>
  );
}

/** Cadastro (sem `tcc`) ou edição dos dados do TCC. */
function TccForm({ tcc, onCreated }: { tcc?: Tcc; onCreated?: () => void }) {
  const { data: advisors } = useUsers('ADVISOR');
  const { data: areas } = useThematicAreas();
  const { data: keywords } = useKeywords();
  const createTcc = useCreateTcc();
  const updateTcc = useUpdateTcc(tcc?.id ?? '');

  const [title, setTitle] = useState('');
  const [abstractText, setAbstractText] = useState('');
  const [advisorId, setAdvisorId] = useState('');
  const [areaIds, setAreaIds] = useState<string[]>([]);
  const [keywordIds, setKeywordIds] = useState<string[]>([]);
  const [message, setMessage] = useState<{ text: string; error: boolean } | null>(null);

  useEffect(() => {
    if (!tcc) return;
    setTitle(tcc.title);
    setAbstractText(tcc.abstract);
    setAdvisorId(tcc.advisor?.id ?? '');
    setAreaIds(tcc.thematicAreas.map((a) => a.id));
    setKeywordIds(tcc.keywords.map((k) => k.id));
  }, [tcc?.id, tcc?.updatedAt]);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setMessage(null);
    const data: TccInput = { title, abstract: abstractText, advisorId, thematicAreaIds: areaIds, keywordIds };
    try {
      if (tcc) {
        await updateTcc.mutateAsync(data);
        setMessage({ text: 'Dados salvos.', error: false });
      } else {
        await createTcc.mutateAsync(data);
        onCreated?.();
      }
    } catch (err) {
      setMessage({ text: apiErrorMessage(err, 'Não foi possível salvar.'), error: true });
    }
  }

  const toggle = (list: string[], id: string) => (list.includes(id) ? list.filter((x) => x !== id) : [...list, id]);
  const pending = createTcc.isPending || updateTcc.isPending;

  return (
    <div className="card">
      <h3>{tcc ? 'Dados do TCC' : 'Cadastrar meu TCC'}</h3>
      {!tcc && <p className="muted">Preencha os dados iniciais. Depois você envia o PDF e submete para o orientador.</p>}
      <form onSubmit={handleSubmit}>
        <div className="form-group">
          <label htmlFor="tcc-title">Título</label>
          <input id="tcc-title" value={title} onChange={(e) => setTitle(e.target.value)} required maxLength={255} />
        </div>
        <div className="form-group">
          <label htmlFor="tcc-abstract">Resumo</label>
          <textarea id="tcc-abstract" value={abstractText} onChange={(e) => setAbstractText(e.target.value)} required />
        </div>
        <div className="form-group">
          <label htmlFor="tcc-advisor">Orientador</label>
          <select id="tcc-advisor" value={advisorId} onChange={(e) => setAdvisorId(e.target.value)}>
            <option value="">Selecione...</option>
            {advisors?.map((a) => (
              <option key={a.id} value={a.id}>
                {a.name}
              </option>
            ))}
          </select>
        </div>
        {!!areas?.length && (
          <div className="form-group">
            <label>Áreas temáticas</label>
            <div className="checkbox-list">
              {areas.map((area) => (
                <label key={area.id}>
                  <input type="checkbox" checked={areaIds.includes(area.id)} onChange={() => setAreaIds(toggle(areaIds, area.id))} />
                  {area.name}
                </label>
              ))}
            </div>
          </div>
        )}
        {!!keywords?.length && (
          <div className="form-group">
            <label>Palavras-chave</label>
            <div className="checkbox-list">
              {keywords.map((kw) => (
                <label key={kw.id}>
                  <input type="checkbox" checked={keywordIds.includes(kw.id)} onChange={() => setKeywordIds(toggle(keywordIds, kw.id))} />
                  {kw.name}
                </label>
              ))}
            </div>
          </div>
        )}
        {message && (
          <p className={message.error ? 'error-text' : 'muted'} role={message.error ? 'alert' : undefined}>
            {message.text}
          </p>
        )}
        <button type="submit" disabled={pending}>
          {tcc ? 'Salvar dados' : pending ? 'Criando...' : 'Criar TCC'}
        </button>
      </form>
    </div>
  );
}

function UploadAndSubmit({ tcc }: { tcc: Tcc }) {
  const uploadDoc = useUploadDocument(tcc.id);
  const submitTcc = useSubmitTcc(tcc.id);
  const [file, setFile] = useState<File | null>(null);
  const [message, setMessage] = useState<{ text: string; error: boolean } | null>(null);
  const isResubmission = tcc.status === 'CORRECTIONS_REQUESTED';

  async function handleUpload() {
    if (!file) return;
    setMessage(null);
    try {
      const updated = await uploadDoc.mutateAsync(file);
      setFile(null);
      setMessage({ text: `PDF enviado (versão ${updated.document?.version}).`, error: false });
    } catch (err) {
      setMessage({ text: apiErrorMessage(err, 'Não foi possível enviar o PDF.'), error: true });
    }
  }

  async function handleSubmit() {
    setMessage(null);
    try {
      await submitTcc.mutateAsync();
    } catch (err) {
      setMessage({ text: apiErrorMessage(err, 'Não foi possível submeter.'), error: true });
    }
  }

  return (
    <div className="card">
      <h3>PDF e envio</h3>
      {isResubmission && <p className="muted">Envie a versão corrigida antes de reenviar. As versões anteriores ficam guardadas.</p>}
      <div className="form-group">
        <label htmlFor="tcc-file">Arquivo PDF (até 20 MB)</label>
        <input id="tcc-file" type="file" accept="application/pdf" onChange={(e) => setFile(e.target.files?.[0] ?? null)} />
      </div>
      <div className="btn-row">
        <button type="button" className="secondary" onClick={handleUpload} disabled={!file || uploadDoc.isPending}>
          {uploadDoc.isPending ? 'Enviando...' : 'Enviar PDF'}
        </button>
        <button type="button" onClick={handleSubmit} disabled={!tcc.permissions.submit || submitTcc.isPending}>
          {isResubmission ? 'Reenviar TCC' : 'Submeter TCC'}
        </button>
      </div>
      {message && (
        <p className={message.error ? 'error-text' : 'muted'} role={message.error ? 'alert' : undefined} style={{ marginTop: '0.75rem' }}>
          {message.text}
        </p>
      )}
    </div>
  );
}
