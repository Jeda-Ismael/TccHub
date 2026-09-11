import { FormEvent, useEffect, useState } from 'react';
import { useCreateTcc, useKeywords, useSubmitTcc, useTccs, useThematicAreas, useUpdateTcc, useUploadDocument, useUsers } from '../../api/hooks';
import { StatusBadge } from '../../components/StatusBadge';

export function StudentHome() {
  const { data: tccs, isLoading } = useTccs();
  const tcc = tccs?.[0];

  if (isLoading) return <p>Carregando...</p>;
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
    <div className="card">
      <h2>Cadastrar meu TCC</h2>
      <p className="muted">Preencha os dados iniciais. Você poderá editar e enviar o PDF depois.</p>
      <form onSubmit={handleSubmit}>
        <div className="form-group">
          <label>Título</label>
          <input value={title} onChange={(e) => setTitle(e.target.value)} required minLength={5} />
        </div>
        <div className="form-group">
          <label>Resumo</label>
          <textarea value={abstractText} onChange={(e) => setAbstractText(e.target.value)} required minLength={20} />
        </div>
        <div className="form-group">
          <label>Orientador</label>
          <select value={advisorId} onChange={(e) => setAdvisorId(e.target.value)}>
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

  if (!tcc) return <p>Carregando...</p>;

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
      <div className="card">
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <h2 style={{ margin: 0 }}>{tcc.title}</h2>
          <StatusBadge status={tcc.status} />
        </div>
        <p className="muted">Versão do documento: {tcc.documentVersion}</p>
        {tcc.documentUrl && (
          <p>
            <a href={`${import.meta.env.VITE_API_URL || 'http://localhost:3000'}${tcc.documentUrl}`} target="_blank" rel="noreferrer">
              Ver PDF enviado
            </a>
          </p>
        )}
        {tcc.projectGrade != null && (
          <p className="muted">
            Nota do projeto: {tcc.projectGrade} · Nota da apresentação: {tcc.presentationGrade}
          </p>
        )}
        {correctionComments.length > 0 && (
          <div className="card" style={{ background: '#fdecea', borderColor: '#f3b7b1' }}>
            <strong>Correções solicitadas pela banca:</strong>
            <ul>
              {correctionComments.map((c) => (
                <li key={c.id}>
                  {c.evaluator.name}: {c.comment}
                </li>
              ))}
            </ul>
          </div>
        )}
      </div>

      {editable && (
        <div className="card">
          <h3>Editar dados</h3>
          <form onSubmit={handleSave}>
            <div className="form-group">
              <label>Título</label>
              <input value={title} onChange={(e) => setTitle(e.target.value)} required minLength={5} />
            </div>
            <div className="form-group">
              <label>Resumo</label>
              <textarea value={abstractText} onChange={(e) => setAbstractText(e.target.value)} required minLength={20} />
            </div>
            <div className="form-group">
              <label>Orientador</label>
              <select value={advisorId} onChange={(e) => setAdvisorId(e.target.value)}>
                <option value="">Selecione...</option>
                {advisors?.map((a) => (
                  <option key={a.id} value={a.id}>
                    {a.name}
                  </option>
                ))}
              </select>
            </div>
            <div className="form-group">
              <label>Áreas temáticas</label>
              {thematicAreas?.map((area) => (
                <label key={area.id} style={{ display: 'inline-flex', alignItems: 'center', gap: 4, marginRight: 12, fontWeight: 400 }}>
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
            <div className="form-group">
              <label>Palavras-chave</label>
              {keywords?.map((kw) => (
                <label key={kw.id} style={{ display: 'inline-flex', alignItems: 'center', gap: 4, marginRight: 12, fontWeight: 400 }}>
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
            <button type="submit" disabled={updateTcc.isPending}>
              Salvar
            </button>
          </form>

          <hr style={{ margin: '1.25rem 0', border: 0, borderTop: '1px solid var(--border)' }} />

          <h3>Arquivo da monografia (PDF)</h3>
          <div className="form-group">
            <input type="file" accept="application/pdf" onChange={(e) => setFile(e.target.files?.[0] || null)} />
          </div>
          <div className="btn-row">
            <button type="button" onClick={handleUpload} disabled={!file || uploadDoc.isPending}>
              Enviar PDF
            </button>
            <button type="button" onClick={handleSubmitTcc} disabled={!tcc.documentUrl || submitTcc.isPending}>
              Submeter TCC
            </button>
          </div>
          {message && <p className="muted" style={{ marginTop: '0.75rem' }}>{message}</p>}
        </div>
      )}
    </div>
  );
}
