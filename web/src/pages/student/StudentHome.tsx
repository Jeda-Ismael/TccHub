import { FormEvent, useEffect, useState } from 'react';
import { apiErrorMessage } from '../../api/client';
import { TccInput, useCreateTcc, useKeywords, useSubmitTcc, useTcc, useTccs, useThematicAreas, useUpdateTcc, useUploadDocument, useUsers } from '../../api/hooks';
import { Tcc, TccStatus } from '../../api/types';
import { BoardList } from '../../components/BoardList';
import { DefenseSection } from '../../components/DefenseSection';
import { FileDropzone } from '../../components/FileDropzone';
import { PublicationCard } from '../../components/PublicationCard';
import { QueryStatus } from '../../components/QueryStatus';
import { StatusHistoryTimeline } from '../../components/StatusHistoryTimeline';
import { TccSummary } from '../../components/TccSummary';
import { toast } from '../../components/Toaster';
import { MeetingsCard } from '../../components/MeetingsCard';
import { MilestonesCard } from '../../components/MilestonesCard';

/** O que o aluno precisa saber/fazer em cada status. */
const NEXT_STEP: Record<TccStatus, string> = {
  DRAFT: 'Preencha os dados, escolha o orientador, escolha o PDF e clique em "Enviar PDF e submeter TCC".',
  SUBMITTED: 'Seu TCC está com o orientador para a primeira revisão.',
  RESUBMITTED: 'Sua nova versão está com o orientador.',
  SENT_TO_EVALUATION: 'A banca está avaliando seu TCC. Os pareceres aparecem quando todos terminarem.',
  CORRECTIONS_REQUESTED: 'Faça as correções pedidas, escolha a nova versão do PDF e clique em "Enviar PDF e reenviar TCC".',
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
      <MeetingsCard tcc={tcc} />
      <MilestonesCard />
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
        toast('Dados salvos.');
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
  // Depois do rascunho os dados quase nunca mudam: o formulário fica fechado até o aluno pedir
  const [expanded, setExpanded] = useState(!tcc || tcc.status === 'DRAFT');

  if (!expanded) {
    return (
      <div className="card card-collapsed">
        <h3>Dados do TCC</h3>
        <button type="button" className="secondary" onClick={() => setExpanded(true)} aria-expanded={false}>
          Editar título, resumo e orientador
        </button>
      </div>
    );
  }

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
  const [error, setError] = useState<string | null>(null);
  const isResubmission = tcc.status === 'CORRECTIONS_REQUESTED';
  const action = isResubmission ? 'reenviar' : 'submeter';
  const pending = uploadDoc.isPending || submitTcc.isPending;

  /** Envia o PDF escolhido (se houver) e depois submete, num clique só. */
  async function handleSend() {
    setError(null);
    let step: 'upload' | 'submit' = 'upload';
    try {
      if (file) {
        await uploadDoc.mutateAsync(file);
        setFile(null);
      }
      step = 'submit';
      await submitTcc.mutateAsync();
      toast(isResubmission ? 'TCC reenviado ao orientador.' : 'TCC submetido ao orientador.');
    } catch (err) {
      setError(apiErrorMessage(err, step === 'upload' ? 'Não foi possível enviar o PDF.' : `Não foi possível ${action}.`));
    }
  }

  /** Guarda o PDF sem submeter ainda (para quem quer revisar antes). */
  async function handleSaveOnly() {
    if (!file) return;
    setError(null);
    try {
      const updated = await uploadDoc.mutateAsync(file);
      setFile(null);
      toast(`PDF salvo (versão ${updated.document?.version}).`);
    } catch (err) {
      setError(apiErrorMessage(err, 'Não foi possível enviar o PDF.'));
    }
  }

  const sendLabel = pending
    ? 'Enviando...'
    : file
      ? isResubmission
        ? 'Enviar PDF e reenviar TCC'
        : 'Enviar PDF e submeter TCC'
      : isResubmission
        ? 'Reenviar TCC'
        : 'Submeter TCC';

  return (
    <div className="card">
      <h3>PDF e envio</h3>
      <p className="muted">
        {isResubmission
          ? 'Escolha a versão corrigida e clique em reenviar. As versões anteriores ficam guardadas.'
          : tcc.document
            ? 'O PDF atual já está salvo. Escolha outro só se quiser trocar.'
            : 'Escolha o PDF do TCC e clique em submeter.'}
      </p>
      {tcc.permissions.upload && <FileDropzone id="tcc-file" label="Arquivo PDF" file={file} onChange={setFile} disabled={pending} />}
      <div className="btn-row">
        <button type="button" onClick={handleSend} disabled={pending || (!tcc.permissions.submit && !file)}>
          {sendLabel}
        </button>
        {file && (
          <button type="button" className="secondary" onClick={handleSaveOnly} disabled={pending}>
            Só salvar o PDF
          </button>
        )}
      </div>
      {error && (
        <p className="error-text form-feedback" role="alert">
          {error}
        </p>
      )}
    </div>
  );
}
