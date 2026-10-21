import { FormEvent, useMemo, useState } from 'react';
import { apiErrorMessage } from '../../api/client';
import { useAllUsers, useCreateUser, useDeleteUser, useUpdateUserRoles } from '../../api/hooks';
import { ROLE_LABELS, ROLES, Role, User } from '../../api/types';
import { useAuth } from '../../auth/AuthContext';

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
  const { data: users, isLoading } = useAllUsers();
  const updateRoles = useUpdateUserRoles();
  const createUser = useCreateUser();
  const deleteUser = useDeleteUser();

  const [search, setSearch] = useState('');
  const [roleFilter, setRoleFilter] = useState<Role | ''>('');
  const [error, setError] = useState<string | null>(null);
  const [editing, setEditing] = useState<{ id: string; roles: Role[] } | null>(null);
  const [confirmingDeleteId, setConfirmingDeleteId] = useState<string | null>(null);

  const [newName, setNewName] = useState('');
  const [newEmail, setNewEmail] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [newRoles, setNewRoles] = useState<Role[]>(['ADVISOR']);
  const [createError, setCreateError] = useState<string | null>(null);

  const filtered = useMemo(() => {
    if (!users) return [];
    const term = search.trim().toLowerCase();
    return users.filter((u) => {
      const matchesSearch = !term || u.name.toLowerCase().includes(term) || u.email.toLowerCase().includes(term);
      const matchesRole = !roleFilter || u.roles.includes(roleFilter);
      return matchesSearch && matchesRole;
    });
  }, [users, search, roleFilter]);

  if (isLoading) return <p>Carregando...</p>;

  async function saveRoles() {
    if (!editing) return;
    setError(null);
    try {
      await updateRoles.mutateAsync(editing);
      setEditing(null);
    } catch (err) {
      setError(apiErrorMessage(err, 'Não foi possível alterar os papéis deste usuário.'));
    }
  }

  async function handleDelete(user: User) {
    setError(null);
    try {
      await deleteUser.mutateAsync(user.id);
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
      setNewName('');
      setNewEmail('');
      setNewPassword('');
      setNewRoles(['ADVISOR']);
    } catch (err) {
      setCreateError(apiErrorMessage(err, 'Não foi possível criar o usuário.'));
    }
  }

  return (
    <div>
      <div className="card">
        <h2>Usuários</h2>
        <p className="muted">
          Um professor pode acumular os papéis de Orientador, Avaliador e Coordenação. Aluno não combina com outros papéis. Novos cadastros
          entram como Aluno.
        </p>

        <div className="btn-row" style={{ marginBottom: '1rem' }}>
          <input placeholder="Buscar por nome ou e-mail..." value={search} onChange={(e) => setSearch(e.target.value)} style={{ flex: 1 }} />
          <select value={roleFilter} onChange={(e) => setRoleFilter(e.target.value as Role | '')} style={{ width: 'auto' }} aria-label="Filtrar por papel">
            <option value="">Todos os papéis</option>
            {ROLES.map((r) => (
              <option key={r} value={r}>
                {ROLE_LABELS[r]}
              </option>
            ))}
          </select>
        </div>

        {error && (
          <p className="error-text" role="alert">
            {error}
          </p>
        )}
        {!filtered.length && <p className="muted">Nenhum usuário corresponde à busca.</p>}

        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
          <thead>
            <tr style={{ textAlign: 'left', borderBottom: '1px solid var(--border)' }}>
              <th style={{ padding: '0.5rem 0' }}>Nome</th>
              <th>E-mail</th>
              <th>Papéis</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {filtered.map((u) => (
              <tr key={u.id} style={{ borderBottom: '1px solid var(--border)' }}>
                <td style={{ padding: '0.5rem 0' }}>{u.name}</td>
                <td>{u.email}</td>
                <td>
                  {editing?.id === u.id ? (
                    <span style={{ display: 'inline-flex', flexWrap: 'wrap', alignItems: 'center', gap: 8 }}>
                      <RoleCheckboxes value={editing.roles} onChange={(roles) => setEditing({ id: u.id, roles })} disabled={updateRoles.isPending} />
                      <button type="button" onClick={saveRoles} disabled={updateRoles.isPending || editing.roles.length === 0}>
                        Salvar
                      </button>
                      <button type="button" className="secondary" onClick={() => setEditing(null)} disabled={updateRoles.isPending}>
                        Cancelar
                      </button>
                    </span>
                  ) : (
                    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                      <span className="role-chips">
                        {u.roles.map((role) => (
                          <span key={role} className="role-chip">
                            {ROLE_LABELS[role]}
                          </span>
                        ))}
                      </span>
                      <button type="button" className="secondary" onClick={() => setEditing({ id: u.id, roles: u.roles })}>
                        Editar
                      </button>
                    </span>
                  )}
                </td>
                <td style={{ textAlign: 'right' }}>
                  {u.id === currentUser?.id ? null : confirmingDeleteId === u.id ? (
                    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6, fontSize: '0.85rem' }}>
                      Excluir?
                      <button type="button" className="danger" onClick={() => handleDelete(u)} disabled={deleteUser.isPending}>
                        Confirmar
                      </button>
                      <button type="button" className="secondary" onClick={() => setConfirmingDeleteId(null)}>
                        Cancelar
                      </button>
                    </span>
                  ) : (
                    <button type="button" className="danger" onClick={() => setConfirmingDeleteId(u.id)}>
                      Excluir
                    </button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="card">
        <h3>Criar usuário</h3>
        <p className="muted">Dá acesso a orientadores, avaliadores e coordenadores que ainda não têm conta.</p>
        <form onSubmit={handleCreate}>
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
            <input id="new-user-password" type="password" value={newPassword} onChange={(e) => setNewPassword(e.target.value)} required minLength={8} />
          </div>
          <div className="form-group">
            <label>Papéis</label>
            <RoleCheckboxes value={newRoles} onChange={setNewRoles} />
          </div>
          {createError && (
            <p className="error-text" role="alert">
              {createError}
            </p>
          )}
          <button type="submit" disabled={createUser.isPending || newRoles.length === 0}>
            Criar usuário
          </button>
        </form>
      </div>
    </div>
  );
}
