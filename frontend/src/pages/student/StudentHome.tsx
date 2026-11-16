import { FormEvent, useEffect, useState } from 'react';
import { useCreateTcc, useKeywords, useSubmitTcc, useTccs, useThematicAreas, useUpdateTcc, useUploadDocument, useUsers } from '../../api/hooks';
import { StatusBadge } from '../../components/StatusBadge';
import { TccProgress } from '../../components/TccProgress';
import { DocumentLink } from '../../components/DocumentLink';
import { AlertIcon } from '../../components/icons';

export function StudentHome() {
  const { data: tccs, isLoading } = useTccs();
  const tcc = tccs?.[0];

  if (isLoading) return <p className="loading">Carregando...</p>;
  if (!tcc) return <CreateTccForm />;
  return <TccDetail tccId={tcc.id} />;
}

function CreateTccForm() {
  const [title, setTitle] = useState('');
  const [abstractText, setAbstractText] = useState('');
  const [advisorId, setAdvisorId] = useState('');
  const { data: advisors } = useUsers('ADVISOR');
  const createTcc = useCreateTcc();
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      await createTcc.mutateAsync({ title, abstract: abstractText, advisorId: advisorId || undefined });
    } catch (err: any) {
      setError(err.response?.data?.message || 'Não foi possível criar o TCC');
    }
  }

  return (
    <div>
      <div className="page-head">
        <div className="page-head-text">
          <h1>Cadastrar meu TCC</h1>
          <p>Preencha os dados iniciais. Você poderá editar e enviar o PDF depois.</p>
        </div>
      </div>
      <TccProgress status="DRAFT" />
      <div className="card">
        <form onSubmit={handleSubmit}>
          <div className="form-group">
            <label htmlFor="new-title">Título</label>
            <input id="new-title" value={title} onChange={(e) => setTitle(e.target.value)} required minLength={5} />
          </div>
          <div className="form-group">
            <label htmlFor="new-abstract">Resumo</label>
            <textarea id="new-abstract" value={abstractText} onChange={(e) => setAbstractText(e.target.value)} required minLength={20} />
            <span className="field-hint">Mínimo de 20 caracteres.</span>
          </div>
          <div className="form-group">
            <label htmlFor="new-advisor">Orientador</label>
            <select id="new-advisor" value={advisorId} onChange={(e) => setAdvisorId(e.target.value)}>
              <option value="">Selecione...</option>
              {advisors?.map((a) => (
                <option key={a.id} value={a.id}>
                  {a.name}
                </option>
              ))}
            </select>
          </div>
          {error && <p className="error-text">{error}</p>}
          <button type="submit" disabled={createTcc.isPending}>
            {createTcc.isPending ? 'Criando...' : 'Criar TCC'}
          </button>
        </form>
      </div>
    </div>
  );
}

function TccDetail({ tccId }: { tccId: string }) {
  const { data: tccs } = useTccs();
  const tcc = tccs?.find((t) => t.id === tccId);
  const { data: advisors } = useUsers('ADVISOR');
  const { data: thematicAreas } = useThematicAreas();
  const { data: keywords } = useKeywords();
  const updateTcc = useUpdateTcc(tccId);
  const uploadDoc = useUploadDocument(tccId);
  const submitTcc = useSubmitTcc(tccId);

  const [title, setTitle] = useState('');
  const [abstractText, setAbstractText] = useState('');
  const [advisorId, setAdvisorId] = useState('');
  const [selectedAreas, setSelectedAreas] = useState<string[]>([]);
  const [selectedKeywords, setSelectedKeywords] = useState<string[]>([]);
  const [file, setFile] = useState<File | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  useEffect(() => {
    if (!tcc) return;
    setTitle(tcc.title);
    setAbstractText(tcc.abstract);
    setAdvisorId(tcc.advisor?.id || '');
    setSelectedAreas(tcc.thematicAreas.map((t) => t.thematicArea.id));
    setSelectedKeywords(tcc.keywords.map((k) => k.keyword.id));
  }, [tcc?.id, tcc?.updatedAt]);

  if (!tcc) return <p className="loading">Carregando...</p>;

  const editable = tcc.status === 'DRAFT' || tcc.status === 'CORRECTIONS_REQUESTED';

  async function handleSave(e: FormEvent) {
    e.preventDefault();
    setMessage(null);
    await updateTcc.mutateAsync({
      title,
      abstract: abstractText,
      advisorId: advisorId || undefined,
      thematicAreaIds: selectedAreas,
      keywordIds: selectedKeywords,
    });
    setMessage('Dados salvos.');
  }

  async function handleUpload() {
    if (!file) return;
    setMessage(null);
    await uploadDoc.mutateAsync(file);
    setFile(null);
    setMessage('Arquivo enviado.');
  }

  async function handleSubmitTcc() {
    setMessage(null);
    try {
      await submitTcc.mutateAsync();
      setMessage('TCC submetido.');
    } catch (err: any) {
      setMessage(err.response?.data?.message || 'Não foi possível submeter');
    }
  }

  const correctionComments = tcc.evaluators.filter((e) => e.verdict === 'CORRECTIONS_REQUESTED' && e.comment);

  return (
    <div>
      <div className="page-head">
        <div className="page-head-text">
          <h1>{tcc.title}</h1>
          <p>Acompanhe o andamento do seu trabalho e mantenha os dados atualizados.</p>
        </div>
        <StatusBadge status={tcc.status} />
      </div>

      <TccProgress status={tcc.status} />

      {correctionComments.length > 0 && (
        <div className="notice danger" role="status">
          <AlertIcon size={20} />
          <div>
            <strong>Correções solicitadas pela banca</strong>
            <ul>
              {correctionComments.map((c) => (
                <li key={c.id}>
                  <b>{c.evaluator.name}:</b> {c.comment}
                </li>
              ))}
            </ul>
          </div>
        </div>
      )}

      <dl className="meta-list">
        <div>
          <dt>Orientador</dt>
          <dd>{tcc.advisor?.name || <span className="muted">Não definido</span>}</dd>
        </div>
        <div>
          <dt>Documento</dt>
          <dd>
            {tcc.documentUrl ? (
              <DocumentLink url={tcc.documentUrl}>Ver PDF enviado (v{tcc.documentVersion})</DocumentLink>
            ) : (
              <span className="muted">Nenhum PDF enviado</span>
            )}
          </dd>
        </div>
        {tcc.projectGrade != null && (
          <div>
            <dt>Nota do projeto</dt>
            <dd>{tcc.projectGrade}</dd>
          </div>
        )}
        {tcc.projectGrade != null && (
          <div>
            <dt>Nota da apresentação</dt>
            <dd>{tcc.presentationGrade}</dd>
          </div>
        )}
      </dl>

      {editable && (
        <div className="card">
          <h3>Dados do trabalho</h3>
          <form onSubmit={handleSave}>
            <div className="form-group">
              <label htmlFor="tcc-title">Título</label>
              <input id="tcc-title" value={title} onChange={(e) => setTitle(e.target.value)} required minLength={5} />
            </div>
            <div className="form-group">
              <label htmlFor="tcc-abstract">Resumo</label>
              <textarea id="tcc-abstract" value={abstractText} onChange={(e) => setAbstractText(e.target.value)} required minLength={20} />
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
            <fieldset className="fieldset">
              <legend className="legend">Áreas temáticas</legend>
              <div className="chip-group">
                {thematicAreas?.map((area) => (
                  <label key={area.id} className="chip">
                    <input
                      type="checkbox"
                      checked={selectedAreas.includes(area.id)}
                      onChange={(e) =>
                        setSelectedAreas((prev) => (e.target.checked ? [...prev, area.id] : prev.filter((id) => id !== area.id)))
                      }
                    />
                    {area.name}
                  </label>
                ))}
              </div>
            </fieldset>
            <fieldset className="fieldset">
              <legend className="legend">Palavras-chave</legend>
              <div className="chip-group">
                {keywords?.map((kw) => (
                  <label key={kw.id} className="chip">
                    <input
                      type="checkbox"
                      checked={selectedKeywords.includes(kw.id)}
                      onChange={(e) =>
                        setSelectedKeywords((prev) => (e.target.checked ? [...prev, kw.id] : prev.filter((id) => id !== kw.id)))
                      }
                    />
                    {kw.name}
                  </label>
                ))}
              </div>
            </fieldset>
            <button type="submit" disabled={updateTcc.isPending}>
              Salvar dados
            </button>
          </form>

          <hr className="divider" />

          <h3>Monografia em PDF</h3>
          <p className="card-sub">Envie o arquivo e depois submeta o TCC para o orientador revisar.</p>
          <div className="form-group">
            <input type="file" accept="application/pdf" aria-label="Arquivo PDF da monografia" onChange={(e) => setFile(e.target.files?.[0] || null)} />
          </div>
          <div className="btn-row">
            <button type="button" className="secondary" onClick={handleUpload} disabled={!file || uploadDoc.isPending}>
              Enviar PDF
            </button>
            <button type="button" onClick={handleSubmitTcc} disabled={!tcc.documentUrl || submitTcc.isPending}>
              Submeter TCC
            </button>
          </div>
          {message && <p className="feedback" role="status">{message}</p>}
        </div>
      )}

      {!editable && (
        <div className="card">
          <h3>Resumo</h3>
          <p className="abstract">{tcc.abstract}</p>
        </div>
      )}
    </div>
  );
}
