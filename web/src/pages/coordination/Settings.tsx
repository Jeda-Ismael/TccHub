import { FormEvent, ReactNode, useEffect, useState } from 'react';
import { apiErrorMessage } from '../../api/client';
import { useSettings, useUpdateSettings } from '../../api/hooks';
import { QueryStatus } from '../../components/QueryStatus';
import { toast } from '../../components/Toaster';
import { CoordHead } from './CoordinationLayout';

export function Settings() {
  const { data: settings, isLoading, error } = useSettings();
  const updateSettings = useUpdateSettings();
  const [boardMinSize, setBoardMinSize] = useState('');
  const [boardMaxSize, setBoardMaxSize] = useState('');
  const [passingGrade, setPassingGrade] = useState('');
  const [message, setMessage] = useState<string | null>(null);

  useEffect(() => {
    if (!settings) return;
    setBoardMinSize(String(settings.boardMinSize));
    setBoardMaxSize(String(settings.boardMaxSize));
    setPassingGrade(String(settings.passingGrade));
  }, [settings]);

  if (!settings) return <QueryStatus isLoading={isLoading} error={error} />;

  const min = Number(boardMinSize);
  const max = Number(boardMaxSize);
  const grade = Number(passingGrade);
  const dirty = min !== settings.boardMinSize || max !== settings.boardMaxSize || grade !== settings.passingGrade;
  const seats = Number.isFinite(max) ? Math.min(Math.max(max, 0), 10) : 0;

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setMessage(null);
    try {
      await updateSettings.mutateAsync({ boardMinSize: min, boardMaxSize: max, passingGrade: grade });
      toast('Configurações salvas.');
    } catch (err) {
      setMessage(apiErrorMessage(err, 'Não foi possível salvar.'));
    }
  }

  return (
    <div>
      <CoordHead title="Configurações">Regras que valem para todos os TCCs: tamanho da banca e nota para aprovação.</CoordHead>

      <form onSubmit={handleSubmit} className="card settings-card">
        <section className="settings-section">
          <div className="settings-section-text">
            <h2>Banca avaliadora</h2>
            <p>Quantos avaliadores a coordenação escolhe para cada TCC, além do orientador.</p>
            <div className="seat-preview" aria-hidden="true">
              {Array.from({ length: seats }, (_, i) => (
                <span key={i} className={i < min ? 'seat required' : 'seat optional'} />
              ))}
            </div>
            {min > 0 && max >= min && (
              <p className="settings-summary">{min === max ? `Toda banca terá ${min} avaliador${min === 1 ? '' : 'es'}.` : `Cada banca terá de ${min} a ${max} avaliadores.`}</p>
            )}
          </div>
          <div className="settings-fields">
            <SettingRow id="board-min" label="Mínimo de avaliadores na banca" hint="A banca só pode ser salva com pelo menos este número.">
              <input id="board-min" type="number" min={1} max={10} value={boardMinSize} onChange={(e) => setBoardMinSize(e.target.value)} required />
            </SettingRow>
            <SettingRow id="board-max" label="Máximo de avaliadores na banca" hint="Limite de avaliadores por TCC.">
              <input id="board-max" type="number" min={1} max={10} value={boardMaxSize} onChange={(e) => setBoardMaxSize(e.target.value)} required />
            </SettingRow>
          </div>
        </section>

        <section className="settings-section">
          <div className="settings-section-text">
            <h2>Defesa</h2>
            <p>A média das notas da banca precisa chegar a este valor para o TCC ser aprovado.</p>
            <div className="grade-scale" aria-hidden="true">
              <span className="grade-pass" style={{ left: `${Math.min(Math.max(grade, 0), 10) * 10}%` }} />
              <span className="grade-marker" style={{ left: `${Math.min(Math.max(grade, 0), 10) * 10}%` }}>
                {Number.isFinite(grade) ? grade.toLocaleString('pt-BR') : ''}
              </span>
              <span className="grade-ends">
                <span>0</span>
                <span>10</span>
              </span>
            </div>
          </div>
          <div className="settings-fields">
            <SettingRow id="passing-grade" label="Nota mínima para aprovação na defesa" hint="De 0 a 10, com uma casa decimal.">
              <input id="passing-grade" type="number" min={0} max={10} step={0.1} value={passingGrade} onChange={(e) => setPassingGrade(e.target.value)} required />
            </SettingRow>
          </div>
        </section>

        <footer className="settings-footer">
          {message ? (
            <p className="error-text" role="alert">
              {message}
            </p>
          ) : (
            <span className="muted">{dirty ? 'Há alterações não salvas.' : 'Tudo salvo.'}</span>
          )}
          <button type="submit" disabled={updateSettings.isPending}>
            Salvar alterações
          </button>
        </footer>
      </form>
    </div>
  );
}

function SettingRow({ id, label, hint, children }: { id: string; label: string; hint: string; children: ReactNode }) {
  return (
    <div className="setting-row">
      <div>
        <label htmlFor={id}>{label}</label>
        <span className="field-hint">{hint}</span>
      </div>
      {children}
    </div>
  );
}
