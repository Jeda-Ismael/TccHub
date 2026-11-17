import { apiErrorMessage } from '../../api/client';
import { FormEvent, useState } from 'react';
import { useCreatePeriod, useDeletePeriod, usePeriods, useUpdatePeriod } from '../../api/hooks';
import { SubmissionPeriod } from '../../api/types';
import { toast } from '../../components/Toaster';
import { formatDate } from '../../utils/dates';
import { Milestones } from './Milestones';

// A API já diz se o prazo está aberto agora (no relógio do servidor)
function isActive(period: SubmissionPeriod) {
  return period.active;
}

// A API devolve as datas no fuso da instituição (ex.: 2026-08-01T00:00:00-03:00).
// Formatar via toLocaleDateString() as reinterpretaria no fuso do navegador e
// poderia mostrar outro dia: usamos o AAAA-MM-DD como veio.
function formatDateOnly(iso: string) {
  const [year, month, day] = iso.slice(0, 10).split('-');
  return `${day}/${month}/${year}`;
}

export function Periods() {
  const { data: periods } = usePeriods();
  const createPeriod = useCreatePeriod();
  const updatePeriod = useUpdatePeriod();
  const deletePeriod = useDeletePeriod();

  const [editingId, setEditingId] = useState<string | null>(null);
  const [type, setType] = useState('SUBMISSION');
  const [label, setLabel] = useState('');
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [confirmingDeleteId, setConfirmingDeleteId] = useState<string | null>(null);

  function resetForm() {
    setEditingId(null);
    setType('SUBMISSION');
    setLabel('');
    setStartDate('');
    setEndDate('');
  }

  function startEdit(p: SubmissionPeriod) {
    setEditingId(p.id);
    setType(p.type);
    setLabel(p.label);
    setStartDate(p.startDate.slice(0, 10));
    setEndDate(p.endDate.slice(0, 10));
    setError(null);
  }

  async function handleDelete(p: SubmissionPeriod) {
    await deletePeriod.mutateAsync(p.id);
    setConfirmingDeleteId(null);
    toast('Prazo excluído.');
    if (editingId === p.id) resetForm();
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      if (editingId) {
        await updatePeriod.mutateAsync({ id: editingId, data: { type, label, startDate, endDate } });
      } else {
        await createPeriod.mutateAsync({ type, label, startDate, endDate });
      }
      toast(editingId ? 'Prazo atualizado.' : 'Prazo criado.');
      resetForm();
    } catch (err) {
      setError(apiErrorMessage(err, 'Não foi possível salvar o prazo'));
    }
  }

  return (
    <div>
      <div className="card">
        <h2>Prazos</h2>
        {!periods?.length && <p className="muted">Nenhum prazo cadastrado.</p>}
        {periods?.map((p) => (
          <div key={p.id} className="tcc-list-item">
            <div>
              <h3>
                {p.type === 'SUBMISSION' ? 'Submissão' : 'Avaliação'} — {p.label}{' '}
                {isActive(p) && <span className="status-badge status-APPROVED">Ativo agora</span>}
              </h3>
              <p>
                {formatDateOnly(p.startDate)} até {formatDateOnly(p.endDate)}
              </p>
            </div>
            {confirmingDeleteId === p.id ? (
              <div className="btn-row">
                <span className="muted">
                  Confirma exclusão?
                </span>
                <button type="button" className="danger" onClick={() => handleDelete(p)}>
                  Confirmar
                </button>
                <button type="button" className="secondary" onClick={() => setConfirmingDeleteId(null)}>
                  Cancelar
                </button>
              </div>
            ) : (
              <div className="btn-row">
                <button type="button" className="secondary" onClick={() => startEdit(p)}>
                  Editar
                </button>
                <button type="button" className="danger" onClick={() => setConfirmingDeleteId(p.id)}>
                  Excluir
                </button>
              </div>
            )}
          </div>
        ))}
      </div>

      <div className="card">
        <h3>{editingId ? 'Editar prazo' : 'Novo prazo'}</h3>
        <form onSubmit={handleSubmit}>
          <div className="form-group">
            <label htmlFor="period-type">Tipo</label>
            <select id="period-type" value={type} onChange={(e) => setType(e.target.value)}>
              <option value="SUBMISSION">Submissão</option>
              <option value="EVALUATION">Avaliação</option>
            </select>
          </div>
          <div className="form-group">
            <label htmlFor="period-label">Rótulo (ex: 2026/1)</label>
            <input id="period-label" value={label} onChange={(e) => setLabel(e.target.value)} required />
          </div>
          <div className="form-group">
            <label htmlFor="period-start">Início</label>
            <input id="period-start" type="date" value={startDate} onChange={(e) => setStartDate(e.target.value)} required aria-describedby="period-start-preview" />
            <p id="period-start-preview" className="field-hint">
              {startDate ? formatDate(startDate) : 'dd/mm/aaaa'}
            </p>
          </div>
          <div className="form-group">
            <label htmlFor="period-end">Fim</label>
            <input id="period-end" type="date" value={endDate} onChange={(e) => setEndDate(e.target.value)} required aria-describedby="period-end-preview" />
            <p id="period-end-preview" className="field-hint">
              {endDate ? formatDate(endDate) : 'dd/mm/aaaa'}
            </p>
          </div>
          {error && (
            <p className="error-text" role="alert">
              {error}
            </p>
          )}
          <div className="btn-row">
            <button type="submit" disabled={createPeriod.isPending || updatePeriod.isPending}>
              {editingId ? 'Salvar alterações' : 'Criar prazo'}
            </button>
            {editingId && (
              <button type="button" className="secondary" onClick={resetForm}>
                Cancelar edição
              </button>
            )}
          </div>
        </form>
      </div>

      <Milestones />
    </div>
  );
}
