import { FormEvent, useState } from 'react';
import { apiErrorMessage } from '../../api/client';
import { useCreateMilestone, useDeleteMilestone, useMilestones, useUpdateMilestone } from '../../api/hooks';
import { Milestone } from '../../api/types';
import { toast } from '../../components/Toaster';
import { formatDate } from '../../utils/dates';

/** Cronograma do TCC (projeto, qualificação, versão final...), que alunos e orientadores veem. */
export function Milestones() {
  const { data } = useMilestones();
  const milestones = Array.isArray(data) ? data : [];
  const create = useCreateMilestone();
  const update = useUpdateMilestone();
  const remove = useDeleteMilestone();

  const [editingId, setEditingId] = useState<string | null>(null);
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [dueDate, setDueDate] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [confirmingDeleteId, setConfirmingDeleteId] = useState<string | null>(null);

  function resetForm() {
    setEditingId(null);
    setName('');
    setDescription('');
    setDueDate('');
    setError(null);
  }

  function startEdit(m: Milestone) {
    setEditingId(m.id);
    setName(m.name);
    setDescription(m.description ?? '');
    setDueDate(m.dueDate);
    setError(null);
  }

  async function handleDelete(m: Milestone) {
    await remove.mutateAsync(m.id);
    setConfirmingDeleteId(null);
    toast('Etapa excluída.');
    if (editingId === m.id) resetForm();
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    const input = { name, description: description.trim() || undefined, dueDate };
    try {
      if (editingId) {
        await update.mutateAsync({ id: editingId, ...input });
      } else {
        await create.mutateAsync(input);
      }
      toast(editingId ? 'Etapa atualizada.' : 'Etapa criada.');
      resetForm();
    } catch (err) {
      setError(apiErrorMessage(err, 'Não foi possível salvar a etapa.'));
    }
  }

  return (
    <div className="card">
      <h2>Cronograma do TCC</h2>
      <p className="card-sub">As etapas aparecem para alunos e orientadores, com a próxima entrega em destaque.</p>
      {!milestones.length && <p className="muted">Nenhuma etapa cadastrada.</p>}
      {milestones.map((m) => (
        <div key={m.id} className="tcc-list-item">
          <div>
            <h3>
              {m.name} {m.status === 'NEXT' && <span className="status-badge status-SUBMITTED">Próxima</span>}
            </h3>
            <p>
              Até {formatDate(m.dueDate)}
              {m.description && <span className="muted"> · {m.description}</span>}
            </p>
          </div>
          {confirmingDeleteId === m.id ? (
            <div className="btn-row">
              <span className="muted">Confirma exclusão?</span>
              <button type="button" className="danger" onClick={() => handleDelete(m)}>
                Confirmar
              </button>
              <button type="button" className="secondary" onClick={() => setConfirmingDeleteId(null)}>
                Cancelar
              </button>
            </div>
          ) : (
            <div className="btn-row">
              <button type="button" className="secondary" onClick={() => startEdit(m)} aria-label={`Editar etapa ${m.name}`}>
                Editar
              </button>
              <button type="button" className="danger" onClick={() => setConfirmingDeleteId(m.id)} aria-label={`Excluir etapa ${m.name}`}>
                Excluir
              </button>
            </div>
          )}
        </div>
      ))}

      <form onSubmit={handleSubmit} className="meeting-form">
        <h4>{editingId ? 'Editar etapa' : 'Nova etapa'}</h4>
        <div className="form-group">
          <label htmlFor="milestone-name">Nome (ex: Qualificação)</label>
          <input id="milestone-name" value={name} onChange={(e) => setName(e.target.value)} required maxLength={120} />
        </div>
        <div className="form-group">
          <label htmlFor="milestone-description">O que entregar (opcional)</label>
          <input id="milestone-description" value={description} onChange={(e) => setDescription(e.target.value)} maxLength={1000} />
        </div>
        <div className="form-group">
          <label htmlFor="milestone-date">Data limite</label>
          <input id="milestone-date" type="date" value={dueDate} onChange={(e) => setDueDate(e.target.value)} required aria-describedby="milestone-date-preview" />
          <p id="milestone-date-preview" className="field-hint">
            {dueDate ? formatDate(dueDate) : 'dd/mm/aaaa'}
          </p>
        </div>
        {error && (
          <p className="error-text" role="alert">
            {error}
          </p>
        )}
        <div className="btn-row">
          <button type="submit" disabled={create.isPending || update.isPending}>
            {editingId ? 'Salvar etapa' : 'Criar etapa'}
          </button>
          {editingId && (
            <button type="button" className="secondary" onClick={resetForm}>
              Cancelar edição
            </button>
          )}
        </div>
      </form>
    </div>
  );
}
