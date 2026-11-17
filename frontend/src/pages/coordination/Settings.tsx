import { FormEvent, useEffect, useState } from 'react';
import { apiErrorMessage } from '../../api/client';
import { useSettings, useUpdateSettings } from '../../api/hooks';
import { QueryStatus } from '../../components/QueryStatus';

export function Settings() {
  const { data: settings, isLoading, error } = useSettings();
  const updateSettings = useUpdateSettings();
  const [boardMinSize, setBoardMinSize] = useState('');
  const [boardMaxSize, setBoardMaxSize] = useState('');
  const [passingGrade, setPassingGrade] = useState('');
  const [message, setMessage] = useState<{ text: string; error: boolean } | null>(null);

  useEffect(() => {
    if (!settings) return;
    setBoardMinSize(String(settings.boardMinSize));
    setBoardMaxSize(String(settings.boardMaxSize));
    setPassingGrade(String(settings.passingGrade));
  }, [settings]);

  if (!settings) return <QueryStatus isLoading={isLoading} error={error} />;

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setMessage(null);
    try {
      await updateSettings.mutateAsync({ boardMinSize: Number(boardMinSize), boardMaxSize: Number(boardMaxSize), passingGrade: Number(passingGrade) });
      setMessage({ text: 'Configurações salvas.', error: false });
    } catch (err) {
      setMessage({ text: apiErrorMessage(err, 'Não foi possível salvar.'), error: true });
    }
  }

  return (
    <div className="card">
      <h2>Configurações</h2>
      <form onSubmit={handleSubmit}>
        <div className="form-group">
          <label htmlFor="board-min">Mínimo de avaliadores na banca</label>
          <input id="board-min" type="number" min={1} max={10} value={boardMinSize} onChange={(e) => setBoardMinSize(e.target.value)} required />
        </div>
        <div className="form-group">
          <label htmlFor="board-max">Máximo de avaliadores na banca</label>
          <input id="board-max" type="number" min={1} max={10} value={boardMaxSize} onChange={(e) => setBoardMaxSize(e.target.value)} required />
        </div>
        <div className="form-group">
          <label htmlFor="passing-grade">Nota mínima para aprovação na defesa</label>
          <input id="passing-grade" type="number" min={0} max={10} step={0.1} value={passingGrade} onChange={(e) => setPassingGrade(e.target.value)} required />
        </div>
        {message && (
          <p className={message.error ? 'error-text' : 'muted'} role={message.error ? 'alert' : undefined}>
            {message.text}
          </p>
        )}
        <button type="submit" disabled={updateSettings.isPending}>
          Salvar
        </button>
      </form>
    </div>
  );
}
