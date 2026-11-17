import { FormEvent, useState } from 'react';
import { Avatar } from '../../components/Avatar';
import { PlusIcon, SearchIcon } from '../../components/icons';
import { Drawer } from '../../components/Drawer';
import { EmptyState } from '../../components/EmptyState';
import { CoordHead } from './CoordinationLayout';
import { apiErrorMessage } from '../../api/client';
import { useCreateUser, useDeleteUser, useSetUserActive, useUpdateUserRoles, useUserPage } from '../../api/hooks';
import { Pager } from '../../components/Pager';
import { useDebouncedValue } from '../../utils/useDebouncedValue';
import { ROLE_LABELS, ROLES, Role, User } from '../../api/types';
import { useAuth } from '../../auth/AuthContext';
import { toast } from '../../components/Toaster';

/**
 * Aluno é exclusivo: marcar Aluno desmarca os outros papéis, e marcar
 * qualquer outro desmarca Aluno (mesma regra da API).
 */
export function toggleRole(current: Role[], role: Role): Role[] {
  if (current.includes(role)) return current.filter((r) => r !== role);
  if (role === 'STUDENT') return ['STUDENT'];
  return ROLES.filter((r) => r !== 'STUDENT' && (r === role || current.includes(r)));
}

export function RoleCheckboxes({ value, onChange, disabled }: { value: Role[]; onChange: (roles: Role[]) => void; disabled?: boolean }) {
  return (
    <div className="role-checkboxes">
      {ROLES.map((role) => (
        <label key={role}>
          <input type="checkbox" checked={value.includes(role)} onChange={() => onChange(toggleRole(value, role))} disabled={disabled} />
          {ROLE_LABELS[role]}
        </label>
      ))}
    </div>
  );
}

export function UsersManagement() {
  const { user: currentUser } = useAuth();
  const [search, setSearch] = useState('');
  const [roleFilter, setRoleFilter] = useState<Role | ''>('');
  const [page, setPage] = useState(1);
  const q = useDebouncedValue(search.trim());
  // Busca, filtro de papel e paginação ficam na API
  const { data, isLoading } = useUserPage({ q, role: roleFilter, page });
  const setActive = useSetUserActive();
  const updateRoles = useUpdateUserRoles();
  const createUser = useCreateUser();
  const deleteUser = useDeleteUser();

  const [error, setError] = useState<string | null>(null);
  const [editing, setEditing] = useState<{ id: string; roles: Role[] } | null>(null);
  const [confirmingDeleteId, setConfirmingDeleteId] = useState<string | null>(null);

  const [newName, setNewName] = useState('');
  const [newEmail, setNewEmail] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [newRoles, setNewRoles] = useState<Role[]>(['ADVISOR']);
  const [createError, setCreateError] = useState<string | null>(null);
  const [creating, setCreating] = useState(false);

  const filtered = data?.items ?? [];

  if (isLoading) return <p>Carregando...</p>;

  async function saveRoles() {
    if (!editing) return;
    setError(null);
    try {
      await updateRoles.mutateAsync(editing);
      toast('Papéis atualizados.');
      setEditing(null);
    } catch (err) {
      setError(apiErrorMessage(err, 'Não foi possível alterar os papéis deste usuário.'));
    }
  }

  async function toggleActive(user: User) {
    setError(null);
    const active = user.active === false;
    try {
      await setActive.mutateAsync({ id: user.id, active });
      toast(active ? `${user.name} foi reativado.` : `${user.name} foi desativado.`);
    } catch (err) {
      setError(apiErrorMessage(err, 'Não foi possível mudar o acesso deste usuário.'));
    }
  }

  async function handleDelete(user: User) {
    setError(null);
    try {
      await deleteUser.mutateAsync(user.id);
      toast('Usuário excluído.');
    } catch (err) {
      setError(apiErrorMessage(err, 'Não foi possível excluir este usuário.'));
    } finally {
      setConfirmingDeleteId(null);
    }
  }

  async function handleCreate(e: FormEvent) {
    e.preventDefault();
    setCreateError(null);
    try {
      await createUser.mutateAsync({ name: newName, email: newEmail, password: newPassword, roles: newRoles });
      toast('Usuário criado.');
      setNewName('');
      setNewEmail('');
      setNewPassword('');
      setNewRoles(['ADVISOR']);
      setCreating(false);
    } catch (err) {
      setCreateError(apiErrorMessage(err, 'Não foi possível criar o usuário.'));
    }
  }

  return (
    <div>
      <CoordHead
        title="Usuários"
        aside={data && data.total > 0 ? String(data.total) : undefined}
        actions={
          <button type="button" onClick={() => setCreating(true)}>
            <PlusIcon />
            Novo usuário
          </button>
        }
      >
        Quem tem TCC ou parecer não pode ser excluído: desative para tirar o acesso e manter o histórico.
      </CoordHead>

      <section className="card card-flush">
        <div className="toolbar toolbar-pad">
          <div className="search">
            <SearchIcon />
            <input
              type="search"
              aria-label="Buscar usuário"
              placeholder="Buscar por nome ou e-mail..."
              value={search}
              onChange={(e) => {
                setSearch(e.target.value);
                setPage(1);
              }}
            />
          </div>
          <select
            value={roleFilter}
            onChange={(e) => {
              setRoleFilter(e.target.value as Role | '');
              setPage(1);
            }}
            aria-label="Filtrar por papel"
          >
            <option value="">Todos os papéis</option>
            {ROLES.map((r) => (
              <option key={r} value={r}>
                {ROLE_LABELS[r]}
              </option>
            ))}
          </select>
        </div>

        {error && (
          <p className="error-text table-error" role="alert">
            {error}
          </p>
        )}

        {!filtered.length ? (
          <EmptyState title="Nenhum usuário corresponde à busca.">Tente outro nome, e-mail ou papel.</EmptyState>
        ) : (
          <div className="table-wrap">
            <table className="table users-table">
              <thead>
                <tr>
                  <th>Pessoa</th>
                  <th>Papéis</th>
                  <th>Acesso</th>
                  <th>
                    <span className="visually-hidden">Ações</span>
                  </th>
                </tr>
              </thead>
              <tbody>
                {filtered.map((u) => (
                  <tr key={u.id} className={u.active === false ? 'row-inactive' : undefined}>
                    <td>
                      <span className="cell-person">
                        <Avatar name={u.name} />
                        <span className="cell-person-text">
                          <strong>{u.name}</strong>
                          <span>{u.email}</span>
                        </span>
                      </span>
                    </td>
                    <td>
                      {editing?.id === u.id ? (
                        <span className="roles-editing">
                          <RoleCheckboxes value={editing.roles} onChange={(roles) => setEditing({ id: u.id, roles })} disabled={updateRoles.isPending} />
                          <span className="row-actions">
                            <button type="button" className="small" onClick={saveRoles} disabled={updateRoles.isPending || editing.roles.length === 0}>
                              Salvar
                            </button>
                            <button type="button" className="secondary small" onClick={() => setEditing(null)} disabled={updateRoles.isPending}>
                              Cancelar
                            </button>
                          </span>
                        </span>
                      ) : (
                        <span className="roles-cell">
                          <span className="role-chips">
                            {u.roles.map((role) => (
                              <span key={role} className="role-chip">
                                {ROLE_LABELS[role]}
                              </span>
                            ))}
                          </span>
                          <button type="button" className="link-button" onClick={() => setEditing({ id: u.id, roles: u.roles })}>
                            Editar
                          </button>
                        </span>
                      )}
                    </td>
                    <td>
                      {u.active === false ? <span className="access-pill off">Desativado</span> : <span className="access-pill on">Ativo</span>}
                    </td>
                    <td>
                      {u.id === currentUser?.id ? (
                        <span className="muted you-tag">Você</span>
                      ) : confirmingDeleteId === u.id ? (
                        <span className="row-actions confirming">
                          <span>Excluir?</span>
                          <button type="button" className="danger solid small" onClick={() => handleDelete(u)} disabled={deleteUser.isPending}>
                            Confirmar
                          </button>
                          <button type="button" className="secondary small" onClick={() => setConfirmingDeleteId(null)}>
                            Cancelar
                          </button>
                        </span>
                      ) : (
                        <span className="row-actions">
                          <button type="button" className="secondary small" onClick={() => toggleActive(u)} disabled={setActive.isPending}>
                            {u.active === false ? 'Reativar' : 'Desativar'}
                          </button>
                          <button type="button" className="ghost-danger small" onClick={() => setConfirmingDeleteId(u.id)}>
                            Excluir
                          </button>
                        </span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        {data && (
          <div className="pager-pad">
            <Pager page={data.page} total={data.total} perPage={data.perPage} onChange={setPage} />
          </div>
        )}
      </section>

      <Drawer
        open={creating}
        title="Novo usuário"
        description="Para orientadores, avaliadores e coordenadores que ainda não têm conta. Alunos se cadastram sozinhos."
        onClose={() => setCreating(false)}
      >
        <form onSubmit={handleCreate} className="drawer-form">
          <div className="form-group">
            <label htmlFor="new-user-name">Nome</label>
            <input id="new-user-name" value={newName} onChange={(e) => setNewName(e.target.value)} required />
          </div>
          <div className="form-group">
            <label htmlFor="new-user-email">E-mail</label>
            <input id="new-user-email" type="email" value={newEmail} onChange={(e) => setNewEmail(e.target.value)} required />
          </div>
          <div className="form-group">
            <label htmlFor="new-user-password">Senha inicial</label>
            <input id="new-user-password" type="password" value={newPassword} onChange={(e) => setNewPassword(e.target.value)} required minLength={8} aria-describedby="new-user-password-hint" />
            <p id="new-user-password-hint" className="field-hint">
              Pelo menos 8 caracteres. A pessoa pode trocar depois em "Esqueci minha senha".
            </p>
          </div>
          <fieldset className="form-group plain-fieldset">
            <legend>Papéis</legend>
            <RoleCheckboxes value={newRoles} onChange={setNewRoles} />
            <p className="field-hint">Um professor pode acumular Orientador, Avaliador e Coordenação. Aluno não combina com outros papéis.</p>
          </fieldset>
          {createError && (
            <p className="error-text" role="alert">
              {createError}
            </p>
          )}
          <div className="drawer-actions">
            <button type="button" className="secondary" onClick={() => setCreating(false)}>
              Cancelar
            </button>
            <button type="submit" disabled={createUser.isPending || newRoles.length === 0}>
              Criar usuário
            </button>
          </div>
        </form>
      </Drawer>
    </div>
  );
}
