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
import { toast } from '../../components/Toaster';
import { PlusIcon } from '../../components/icons';
import { CoordHead } from './CoordinationLayout';

export function Catalogs() {
  return (
    <div>
      <CoordHead title="Áreas e palavras-chave">
        Os alunos escolhem destas listas ao cadastrar o TCC, e o repositório usa as mesmas para os filtros. Um item em uso pode ser renomeado, mas não
        excluído.
      </CoordHead>
      <div className="catalog-grid">
        <ThematicAreasCard />
        <KeywordsCard />
      </div>
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
      toast('Área criada.');
      setName('');
    } catch (err) {
      setError(apiErrorMessage(err, 'Não foi possível criar a área temática'));
    }
  }

  async function handleDelete(area: ThematicArea) {
    setError(null);
    try {
      await deleteArea.mutateAsync(area.id);
      toast('Área excluída.');
    } catch (err) {
      setError(apiErrorMessage(err, 'Não foi possível excluir'));
    }
  }

  return (
    <section className="card catalog-card">
      <div className="section-head">
        <h2>Áreas temáticas</h2>
        <span className="count-pill">{areas?.length ?? 0}</span>
      </div>
      <p className="card-sub">Grandes temas, como Inteligência Artificial ou Redes.</p>
      <form onSubmit={handleSubmit} className="add-row">
        <input value={name} onChange={(e) => setName(e.target.value)} placeholder="Nova área temática" aria-label="Nova área temática" />
        <button type="submit" className="secondary" disabled={createArea.isPending}>
          <PlusIcon size={16} />
          Adicionar
        </button>
      </form>
      {error && <p className="error-text">{error}</p>}
      {!areas?.length && <p className="muted catalog-empty">Nenhuma área temática cadastrada.</p>}
      <div className="catalog-list">
        {areas?.map((a) => (
          <CatalogRow
            key={a.id}
            name={a.name}
            usageCount={a.tccCount ?? 0}
            onSave={(newName) => updateArea.mutateAsync({ id: a.id, name: newName })}
            onDelete={() => handleDelete(a)}
          />
        ))}
      </div>
    </section>
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
      toast('Palavra-chave criada.');
      setName('');
    } catch (err) {
      setError(apiErrorMessage(err, 'Não foi possível criar a palavra-chave'));
    }
  }

  async function handleDelete(keyword: Keyword) {
    setError(null);
    try {
      await deleteKeyword.mutateAsync(keyword.id);
      toast('Palavra-chave excluída.');
    } catch (err) {
      setError(apiErrorMessage(err, 'Não foi possível excluir'));
    }
  }

  return (
    <section className="card catalog-card">
      <div className="section-head">
        <h2>Palavras-chave</h2>
        <span className="count-pill">{keywords?.length ?? 0}</span>
      </div>
      <p className="card-sub">Termos específicos que ajudam a encontrar o trabalho no repositório.</p>
      <form onSubmit={handleSubmit} className="add-row">
        <input value={name} onChange={(e) => setName(e.target.value)} placeholder="Nova palavra-chave" aria-label="Nova palavra-chave" />
        <button type="submit" className="secondary" disabled={createKeyword.isPending}>
          <PlusIcon size={16} />
          Adicionar
        </button>
      </form>
      {error && <p className="error-text">{error}</p>}
      {!keywords?.length && <p className="muted catalog-empty">Nenhuma palavra-chave cadastrada.</p>}
      <div className="catalog-list">
        {keywords?.map((k) => (
          <CatalogRow
            key={k.id}
            name={k.name}
            usageCount={k.tccCount ?? 0}
            onSave={(newName) => updateKeyword.mutateAsync({ id: k.id, name: newName })}
            onDelete={() => handleDelete(k)}
          />
        ))}
      </div>
    </section>
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
      <div className="catalog-row confirming">
        <span className="catalog-name">Excluir "{name}"?</span>
        <span className="row-actions">
          <button
            type="button"
            className="danger solid small"
            onClick={() => {
              onDelete();
              setConfirmingDelete(false);
            }}
          >
            Confirmar
          </button>
          <button type="button" className="secondary small" onClick={() => setConfirmingDelete(false)}>
            Cancelar
          </button>
        </span>
      </div>
    );
  }

  if (editing) {
    return (
      <div className="catalog-row editing">
        <input className="catalog-input" value={value} onChange={(e) => setValue(e.target.value)} aria-label={`Novo nome para ${name}`} autoFocus />
        <span className="row-actions">
          <button type="button" className="small" onClick={handleSave} disabled={saving}>
            Salvar
          </button>
          <button
            type="button"
            className="secondary small"
            onClick={() => {
              setValue(name);
              setEditError(null);
              setEditing(false);
            }}
            disabled={saving}
          >
            Cancelar
          </button>
        </span>
        {editError && <p className="error-text row-error">{editError}</p>}
      </div>
    );
  }

  return (
    <div className="catalog-row">
      <span className="catalog-name">{name}</span>
      <span className={usageCount > 0 ? 'usage-pill used' : 'usage-pill'}>{usageCount === 0 ? 'Sem uso' : usageCount === 1 ? '1 TCC' : `${usageCount} TCCs`}</span>
      <span className="row-actions">
        <button type="button" className="secondary small" onClick={() => setEditing(true)}>
          Editar
        </button>
        <button
          type="button"
          className="ghost-danger small"
          onClick={() => setConfirmingDelete(true)}
          disabled={usageCount > 0}
          title={usageCount > 0 ? 'Não é possível excluir: em uso por TCC(s)' : undefined}
        >
          Excluir
        </button>
      </span>
    </div>
  );
}
