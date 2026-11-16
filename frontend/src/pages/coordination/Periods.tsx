import { FormEvent, useState } from 'react'
import { useCreatePeriod, useDeletePeriod, usePeriods, useUpdatePeriod } from '../../api/hooks'
import { SubmissionPeriod } from '../../api/types'

function isActive(period: SubmissionPeriod) {
  const now = new Date()
  return new Date(period.startDate) <= now && now <= new Date(period.endDate)
}

// startDate/endDate são datas de calendário (meia-noite UTC), não instantes.
// Formatar via toLocaleDateString() as reinterpreta no fuso local e pode
// exibir o dia anterior — extraímos o AAAA-MM-DD cru do ISO em vez disso.
function formatDateOnly(iso: string) {
  const [year, month, day] = iso.slice(0, 10).split('-')
  return `${day}/${month}/${year}`
}

export function Periods() {
  const { data: periods } = usePeriods()
  const createPeriod = useCreatePeriod()
  const updatePeriod = useUpdatePeriod()
  const deletePeriod = useDeletePeriod()

  const [editingId, setEditingId] = useState<string | null>(null)
  const [type, setType] = useState('SUBMISSION')
  const [label, setLabel] = useState('')
  const [startDate, setStartDate] = useState('')
  const [endDate, setEndDate] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [confirmingDeleteId, setConfirmingDeleteId] = useState<string | null>(null)

  function resetForm() {
    setEditingId(null)
    setType('SUBMISSION')
    setLabel('')
    setStartDate('')
    setEndDate('')
  }

  function startEdit(p: SubmissionPeriod) {
    setEditingId(p.id)
    setType(p.type)
    setLabel(p.label)
    setStartDate(p.startDate.slice(0, 10))
    setEndDate(p.endDate.slice(0, 10))
    setError(null)
  }

  async function handleDelete(p: SubmissionPeriod) {
    await deletePeriod.mutateAsync(p.id)
    setConfirmingDeleteId(null)
    if (editingId === p.id) resetForm()
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    try {
      if (editingId) {
        await updatePeriod.mutateAsync({ id: editingId, data: { type, label, startDate, endDate } })
      } else {
        await createPeriod.mutateAsync({ type, label, startDate, endDate })
      }
      resetForm()
    } catch (err: any) {
      setError(err.response?.data?.message || 'Não foi possível salvar o prazo')
    }
  }

  return (
    <div>
      <div className="page-head">
        <div className="page-head-text">
          <h1>Prazos</h1>
          <p>Defina as janelas de submissão da monografia e de avaliação pela banca.</p>
        </div>
      </div>
      <div className="card">
        {!periods?.length && <div className="empty">Nenhum prazo cadastrado.</div>}
        <div className="list">
          {periods?.map((p) => (
            <div key={p.id} className="tcc-list-item">
              <div>
                <h3>
                  {p.label}
                  {isActive(p) && <span className="status-badge status-APPROVED">Ativo agora</span>}
                </h3>
                <p>
                  <span className="tag">{p.type === 'SUBMISSION' ? 'Submissão' : 'Avaliação'}</span>
                  {formatDateOnly(p.startDate)} até {formatDateOnly(p.endDate)}
                </p>
              </div>
              {confirmingDeleteId === p.id ? (
                <div className="confirm-inline">
                  Confirma exclusão?
                  <button type="button" className="danger small" onClick={() => handleDelete(p)}>
                    Confirmar
                  </button>
                  <button type="button" className="secondary small" onClick={() => setConfirmingDeleteId(null)}>
                    Cancelar
                  </button>
                </div>
              ) : (
                <div className="btn-row">
                  <button type="button" className="secondary small" onClick={() => startEdit(p)}>
                    Editar
                  </button>
                  <button type="button" className="danger small" onClick={() => setConfirmingDeleteId(p.id)}>
                    Excluir
                  </button>
                </div>
              )}
            </div>
          ))}
        </div>
      </div>

      <div className="card">
        <h3>{editingId ? 'Editar prazo' : 'Novo prazo'}</h3>
        <form onSubmit={handleSubmit}>
          <div className="form-row">
            <div className="form-group">
              <label>Tipo</label>
              <select value={type} onChange={(e) => setType(e.target.value)}>
                <option value="SUBMISSION">Submissão</option>
                <option value="EVALUATION">Avaliação</option>
              </select>
            </div>
            <div className="form-group">
              <label>Rótulo (ex: 2026/1)</label>
              <input value={label} onChange={(e) => setLabel(e.target.value)} required />
            </div>
          </div>
          <div className="form-row">
            <div className="form-group">
              <label>Início</label>
              <input type="date" value={startDate} onChange={(e) => setStartDate(e.target.value)} required />
            </div>
            <div className="form-group">
              <label>Fim</label>
              <input type="date" value={endDate} onChange={(e) => setEndDate(e.target.value)} required />
            </div>
          </div>
          {error && <p className="error-text">{error}</p>}
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
    </div>
  )
}
