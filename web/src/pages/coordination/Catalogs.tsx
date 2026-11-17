import { apiErrorMessage } from '../../api/client';
import { FormEvent, useState } from 'react';
import {
  useCreateKeyword,
  useCreateThematicArea,
  useDeleteKeyword,
  useDeleteThematicArea,
  useKeywords,
  useThematicAreas,
  useUpdateKeyword,
  useUpdateThematicArea,
} from '../../api/hooks';
import { Keyword, ThematicArea } from '../../api/types';

export function Catalogs() {
  return (
    <div>
      <ThematicAreasCard />
      <KeywordsCard />
    </div>
  );
}

function ThematicAreasCard() {
  const { data: areas } = useThematicAreas();
  const createArea = useCreateThematicArea();
  const updateArea = useUpdateThematicArea();
  const deleteArea = useDeleteThematicArea();
  const [name, setName] = useState('');
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    if (!name.trim()) return;
    try {
      await createArea.mutateAsync(name.trim());
      setName('');
    } catch (err) {
      setError(apiErrorMessage(err, 'Não foi possível criar a área temática'));
    }
  }

  async function handleDelete(area: ThematicArea) {
    setError(null);
    try {
      await deleteArea.mutateAsync(area.id);
    } catch (err) {
      setError(apiErrorMessage(err, 'Não foi possível excluir'));
    }
  }

  return (
    <div className="card">
      <h3>Áreas temáticas</h3>
      {areas?.map((a) => (
        <CatalogRow
          key={a.id}
          name={a.name}
          usageCount={a.tccCount ?? 0}
          onSave={(newName) => updateArea.mutateAsync({ id: a.id, name: newName })}
          onDelete={() => handleDelete(a)}
        />
      ))}
      {!areas?.length && <p className="muted">Nenhuma área temática cadastrada.</p>}
      {error && <p className="error-text">{error}</p>}
      <form onSubmit={handleSubmit} className="add-row">
        <input value={name} onChange={(e) => setName(e.target.value)} placeholder="Nova área temática" />
        <button type="submit" disabled={createArea.isPending}>
          Adicionar
        </button>
      </form>
    </div>
  );
}

function KeywordsCard() {
  const { data: keywords } = useKeywords();
  const createKeyword = useCreateKeyword();
  const updateKeyword = useUpdateKeyword();
  const deleteKeyword = useDeleteKeyword();
  const [name, setName] = useState('');
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    if (!name.trim()) return;
    try {
      await createKeyword.mutateAsync(name.trim());
      setName('');
    } catch (err) {
      setError(apiErrorMessage(err, 'Não foi possível criar a palavra-chave'));
    }
  }

  async function handleDelete(keyword: Keyword) {
    setError(null);
    try {
      await deleteKeyword.mutateAsync(keyword.id);
    } catch (err) {
      setError(apiErrorMessage(err, 'Não foi possível excluir'));
    }
  }

  return (
    <div className="card">
      <h3>Palavras-chave</h3>
      {keywords?.map((k) => (
        <CatalogRow
          key={k.id}
          name={k.name}
          usageCount={k.tccCount ?? 0}
          onSave={(newName) => updateKeyword.mutateAsync({ id: k.id, name: newName })}
          onDelete={() => handleDelete(k)}
        />
      ))}
      {!keywords?.length && <p className="muted">Nenhuma palavra-chave cadastrada.</p>}
      {error && <p className="error-text">{error}</p>}
      <form onSubmit={handleSubmit} className="add-row">
        <input value={name} onChange={(e) => setName(e.target.value)} placeholder="Nova palavra-chave" />
        <button type="submit" disabled={createKeyword.isPending}>
          Adicionar
        </button>
      </form>
    </div>
  );
}

function CatalogRow({
  name,
  usageCount,
  onSave,
  onDelete,
}: {
  name: string;
  usageCount: number;
  onSave: (name: string) => Promise<unknown>;
  onDelete: () => void;
}) {
  const [editing, setEditing] = useState(false);
  const [confirmingDelete, setConfirmingDelete] = useState(false);
  const [value, setValue] = useState(name);
  const [saving, setSaving] = useState(false);
  const [editError, setEditError] = useState<string | null>(null);

  async function handleSave() {
    if (!value.trim() || value.trim() === name) {
      setEditing(false);
      return;
    }
    setSaving(true);
    setEditError(null);
    try {
      await onSave(value.trim());
      setEditing(false);
    } catch (err) {
      setEditError(apiErrorMessage(err, 'Não foi possível salvar'));
    } finally {
      setSaving(false);
    }
  }

  if (confirmingDelete) {
    return (
      <div className="row">
        <span className="row-main confirm-inline">
          Excluir "{name}"?
        </span>
        <button
          type="button"
          className="danger"
          onClick={() => {
            onDelete();
            setConfirmingDelete(false);
          }}
        >
          Confirmar
        </button>
        <button type="button" className="secondary" onClick={() => setConfirmingDelete(false)}>
          Cancelar
        </button>
      </div>
    );
  }

  return (
    <div className="row">
      <div className="row-line">
        {editing ? (
          <input className="row-main" value={value} onChange={(e) => setValue(e.target.value)} autoFocus />
        ) : (
          <span className="row-main">
            {name} {usageCount > 0 && <span className="muted">· usado em {usageCount} TCC(s)</span>}
          </span>
        )}
        {editing ? (
          <>
            <button type="button" onClick={handleSave} disabled={saving}>
              Salvar
            </button>
            <button
              type="button"
              className="secondary"
              onClick={() => {
                setValue(name);
                setEditError(null);
                setEditing(false);
              }}
              disabled={saving}
            >
              Cancelar
            </button>
          </>
        ) : (
          <>
            <button type="button" className="secondary" onClick={() => setEditing(true)}>
              Editar
            </button>
            <button
              type="button"
              className="danger"
              onClick={() => setConfirmingDelete(true)}
              disabled={usageCount > 0}
              title={usageCount > 0 ? 'Não é possível excluir: em uso por TCC(s)' : undefined}
            >
              Excluir
            </button>
          </>
        )}
      </div>
      {editError && <p className="error-text row-error">{editError}</p>}
    </div>
  );
}
