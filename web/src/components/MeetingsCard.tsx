import { FormEvent, useState } from 'react';
import { apiErrorMessage } from '../api/client';
import { useConfirmMeeting, useCreateMeeting, useDeleteMeeting, useMeetings } from '../api/hooks';
import { AdvisingMeeting, Tcc } from '../api/types';
import { formatDate } from '../utils/dates';
import { toast } from './Toaster';

/** Hoje no formato do campo de data (AAAA-MM-DD), no relógio do navegador. */
function today() {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
}

/**
 * Reuniões de orientação do TCC. O orientador registra o que foi conversado e o
 * aluno confirma que a reunião aconteceu. Quem vê o TCC vê as reuniões.
 */
export function MeetingsCard({ tcc }: { tcc: Tcc }) {
  const { data, isLoading } = useMeetings(tcc.id);
  const meetings = Array.isArray(data) ? data : [];
  const canRecord = !!tcc.permissions.recordMeeting;
  const [formOpen, setFormOpen] = useState(false);

  // Sem reuniões e sem poder registrar: o cartão não ajuda em nada
  if (isLoading || (!canRecord && !meetings.length)) return null;

  const pending = meetings.filter((m) => m.permissions.confirm).length;

  return (
    <div className="card">
      <div className="card-head">
        <h3>Reuniões de orientação</h3>
        {!!meetings.length && (
          <span className="muted">
            {meetings.length} {meetings.length === 1 ? 'registrada' : 'registradas'}
          </span>
        )}
      </div>
      {pending > 0 && (
        <p className="notice">
          {pending === 1 ? 'Há 1 reunião esperando a sua confirmação.' : `Há ${pending} reuniões esperando a sua confirmação.`}
        </p>
      )}
      {!meetings.length && <p className="muted">Nenhuma reunião registrada ainda.</p>}
      <ul className="meeting-list">
        {meetings.map((m) => (
          <MeetingItem key={m.id} tccId={tcc.id} meeting={m} />
        ))}
      </ul>
      {canRecord &&
        (formOpen ? (
          <MeetingForm tccId={tcc.id} onClose={() => setFormOpen(false)} />
        ) : (
          <button type="button" className="secondary" onClick={() => setFormOpen(true)}>
            Registrar reunião
          </button>
        ))}
    </div>
  );
}

function MeetingItem({ tccId, meeting }: { tccId: string; meeting: AdvisingMeeting }) {
  const confirm = useConfirmMeeting(tccId);
  const remove = useDeleteMeeting(tccId);
  const [error, setError] = useState<string | null>(null);

  async function run(action: () => Promise<unknown>, success: string) {
    setError(null);
    try {
      await action();
      toast(success);
    } catch (err) {
      setError(apiErrorMessage(err, 'Não foi possível concluir.'));
    }
  }

  return (
    <li className="meeting-item">
      <div className="meeting-head">
        <strong>{formatDate(meeting.heldOn)}</strong>
        {meeting.confirmedAt ? (
          <span className="status-badge status-APPROVED">Confirmada pelo aluno</span>
        ) : (
          <span className="status-badge status-SUBMITTED">Aguardando o aluno</span>
        )}
      </div>
      <p className="meeting-summary">{meeting.summary}</p>
      {meeting.nextSteps && (
        <p className="meeting-next">
          <span className="muted">Próximos passos:</span> {meeting.nextSteps}
        </p>
      )}
      {(meeting.permissions.confirm || meeting.permissions.delete) && (
        <div className="btn-row">
          {meeting.permissions.confirm && (
            <button type="button" disabled={confirm.isPending} onClick={() => run(() => confirm.mutateAsync(meeting.id), 'Reunião confirmada.')}>
              Confirmar que aconteceu
            </button>
          )}
          {meeting.permissions.delete && (
            <button type="button" className="link-button" disabled={remove.isPending} onClick={() => run(() => remove.mutateAsync(meeting.id), 'Reunião apagada.')}>
              Apagar
            </button>
          )}
        </div>
      )}
      {error && (
        <p className="error-text" role="alert">
          {error}
        </p>
      )}
    </li>
  );
}

function MeetingForm({ tccId, onClose }: { tccId: string; onClose: () => void }) {
  const create = useCreateMeeting(tccId);
  const [heldOn, setHeldOn] = useState(today());
  const [summary, setSummary] = useState('');
  const [nextSteps, setNextSteps] = useState('');
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      await create.mutateAsync({ heldOn, summary, nextSteps: nextSteps.trim() || undefined });
      toast('Reunião registrada. O aluno vai receber um aviso para confirmar.');
      onClose();
    } catch (err) {
      setError(apiErrorMessage(err, 'Não foi possível registrar a reunião.'));
    }
  }

  return (
    <form onSubmit={handleSubmit} className="meeting-form">
      <h4>Nova reunião</h4>
      <div className="form-group">
        <label htmlFor="meeting-date">Data</label>
        <input id="meeting-date" type="date" value={heldOn} max={today()} onChange={(e) => setHeldOn(e.target.value)} required aria-describedby="meeting-date-preview" />
        <p id="meeting-date-preview" className="field-hint">
          {heldOn ? formatDate(heldOn) : 'dd/mm/aaaa'}
        </p>
      </div>
      <div className="form-group">
        <label htmlFor="meeting-summary">O que foi conversado</label>
        <textarea id="meeting-summary" value={summary} onChange={(e) => setSummary(e.target.value)} required maxLength={5000} />
      </div>
      <div className="form-group">
        <label htmlFor="meeting-next">Próximos passos (opcional)</label>
        <textarea id="meeting-next" value={nextSteps} onChange={(e) => setNextSteps(e.target.value)} maxLength={2000} />
      </div>
      {error && (
        <p className="error-text" role="alert">
          {error}
        </p>
      )}
      <div className="btn-row">
        <button type="submit" disabled={create.isPending}>
          Registrar
        </button>
        <button type="button" className="secondary" onClick={onClose}>
          Cancelar
        </button>
      </div>
    </form>
  );
}
