import { FormEvent, useMemo, useState } from 'react';
import { useCreateUser, useDeleteUser, useUpdateUserRole, useUsers } from '../../api/hooks';
import { Role, UserSummary } from '../../api/types';
import { Avatar } from '../../components/Avatar';
import { SearchIcon } from '../../components/icons';

const ROLES: Role[] = ['STUDENT', 'ADVISOR', 'EVALUATOR', 'COORDINATION'];

const ROLE_LABELS: Record<Role, string> = {
  STUDENT: 'Aluno',
  ADVISOR: 'Orientador',
  EVALUATOR: 'Avaliador',
  COORDINATION: 'Coordenação',
};

export function UsersManagement() {
  const { data: users, isLoading } = useUsers();
  const updateRole = useUpdateUserRole();
  const createUser = useCreateUser();
  const deleteUser = useDeleteUser();

  const [search, setSearch] = useState('');
  const [roleFilter, setRoleFilter] = useState<Role | ''>('');
  const [error, setError] = useState<string | null>(null);
  // Confirmação inline em vez de window.confirm(): um <select> controlado
  // não se "desfaz" sozinho se o usuário cancelar um diálogo nativo bloqueante,
  // porque o navegador já aplicou a seleção no DOM antes do React poder reagir.
  const [pending, setPending] = useState<{ user: UserSummary; newRole: Role } | null>(null);
  const [confirmingDeleteId, setConfirmingDeleteId] = useState<string | null>(null);

  const [newName, setNewName] = useState('');
  const [newEmail, setNewEmail] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [newRole, setNewRole] = useState<Role>('STUDENT');
  const [createError, setCreateError] = useState<string | null>(null);

  const filtered = useMemo(() => {
    if (!users) return [];
    const term = search.trim().toLowerCase();
    return users.filter((u) => {
      const matchesSearch = !term || u.name.toLowerCase().includes(term) || u.email.toLowerCase().includes(term);
      const matchesRole = !roleFilter || u.role === roleFilter;
      return matchesSearch && matchesRole;
    });
  }, [users, search, roleFilter]);

  if (isLoading) return <p className="loading">Carregando...</p>;

  function requestRoleChange(user: UserSummary, role: string) {
    setError(null);
    if (role === user.role) return;
    setPending({ user, newRole: role as Role });
  }

  async function confirmRoleChange() {
    if (!pending) return;
    setError(null);
    try {
      await updateRole.mutateAsync({ id: pending.user.id, role: pending.newRole });
      setPending(null);
    } catch (err: any) {
      setError(err.response?.data?.message || 'Não foi possível alterar o papel deste usuário');
      setPending(null);
    }
  }

  async function handleDelete(user: UserSummary) {
    setError(null);
    try {
      await deleteUser.mutateAsync(user.id);
      setConfirmingDeleteId(null);
    } catch (err: any) {
      setError(err.response?.data?.message || 'Não foi possível excluir este usuário');
      setConfirmingDeleteId(null);
    }
  }

  async function handleCreate(e: FormEvent) {
    e.preventDefault();
    setCreateError(null);
    try {
      await createUser.mutateAsync({ name: newName, email: newEmail, password: newPassword, role: newRole });
      setNewName('');
      setNewEmail('');
      setNewPassword('');
      setNewRole('STUDENT');
    } catch (err: any) {
      setCreateError(err.response?.data?.message || 'Não foi possível criar o usuário');
    }
  }

  return (
    <div>
      <div className="page-head">
        <div className="page-head-text">
          <h1>Usuários</h1>
          <p>Promova um usuário para Orientador, Avaliador ou Coordenação. Novos cadastros (inclusive via Google) entram como Aluno.</p>
        </div>
      </div>

      <div className="card">
        <div className="toolbar">
          <div className="search">
            <SearchIcon />
            <input
              type="search"
              aria-label="Buscar usuário"
              placeholder="Buscar por nome ou email..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>
          <select aria-label="Filtrar por papel" value={roleFilter} onChange={(e) => setRoleFilter(e.target.value as Role | '')}>
            <option value="">Todos os papéis</option>
            {ROLES.map((r) => (
              <option key={r} value={r}>
                {ROLE_LABELS[r]}
              </option>
            ))}
          </select>
        </div>

        {error && <p className="error-text">{error}</p>}
        {!filtered.length && <div className="empty">Nenhum usuário corresponde à busca.</div>}

        {!!filtered.length && (
          <div className="table-wrap">
            <table className="table">
              <thead>
                <tr>
                  <th>Nome</th>
                  <th>Email</th>
                  <th>Papel</th>
                  <th>
                    <span className="sr-only">Ações</span>
                  </th>
                </tr>
              </thead>
              <tbody>
                {filtered.map((u) => (
                  <tr key={u.id}>
                    <td>
                      <span className="cell-person">
                        <Avatar name={u.name} />
                        {u.name}
                      </span>
                    </td>
                    <td className="muted">{u.email}</td>
                    <td>
                      {pending?.user.id === u.id ? (
                        <span className="confirm-inline">
                          Mudar para <strong>{ROLE_LABELS[pending.newRole]}</strong>?
                          <button type="button" className="small" onClick={confirmRoleChange} disabled={updateRole.isPending}>
                            Confirmar
                          </button>
                          <button type="button" className="secondary small" onClick={() => setPending(null)} disabled={updateRole.isPending}>
                            Cancelar
                          </button>
                        </span>
                      ) : (
                        <select
                          aria-label={`Papel de ${u.name}`}
                          value={u.role}
                          onChange={(e) => requestRoleChange(u, e.target.value)}
                          disabled={updateRole.isPending}
                        >
                          {ROLES.map((r) => (
                            <option key={r} value={r}>
                              {ROLE_LABELS[r]}
                            </option>
                          ))}
                        </select>
                      )}
                    </td>
                    <td>
                      {confirmingDeleteId === u.id ? (
                        <span className="confirm-inline">
                          Excluir?
                          <button type="button" className="danger small" onClick={() => handleDelete(u)} disabled={deleteUser.isPending}>
                            Confirmar
                          </button>
                          <button type="button" className="secondary small" onClick={() => setConfirmingDeleteId(null)}>
                            Cancelar
                          </button>
                        </span>
                      ) : (
                        <button type="button" className="danger small" onClick={() => setConfirmingDeleteId(u.id)}>
                          Excluir
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      <div className="card">
        <h3>Criar usuário</h3>
        <p className="card-sub">Cadastra diretamente com um papel já definido. Útil para dar acesso a orientadores e avaliadores que ainda não se registraram.</p>
        <form onSubmit={handleCreate}>
          <div className="form-row">
            <div className="form-group">
              <label htmlFor="new-user-name">Nome</label>
              <input id="new-user-name" value={newName} onChange={(e) => setNewName(e.target.value)} required minLength={2} />
            </div>
            <div className="form-group">
              <label htmlFor="new-user-email">Email</label>
              <input id="new-user-email" type="email" value={newEmail} onChange={(e) => setNewEmail(e.target.value)} required />
            </div>
          </div>
          <div className="form-row">
            <div className="form-group">
              <label htmlFor="new-user-password">Senha inicial</label>
              <input
                id="new-user-password"
                type="password"
                value={newPassword}
                onChange={(e) => setNewPassword(e.target.value)}
                required
                minLength={6}
              />
            </div>
            <div className="form-group">
              <label htmlFor="new-user-role">Papel</label>
              <select id="new-user-role" value={newRole} onChange={(e) => setNewRole(e.target.value as Role)}>
                {ROLES.map((r) => (
                  <option key={r} value={r}>
                    {ROLE_LABELS[r]}
                  </option>
                ))}
              </select>
            </div>
          </div>
          {createError && <p className="error-text">{createError}</p>}
          <button type="submit" disabled={createUser.isPending}>
            Criar usuário
          </button>
        </form>
      </div>
    </div>
  );
}
