import { useQueryClient } from '@tanstack/react-query';
import { createContext, ReactNode, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { api, refreshSession } from '../api/client';
import { AuthResponse, Role, User } from '../api/types';
import { session } from './session';

const ACTIVE_ROLE_KEY = 'hubtcc_active_role';

type Status = 'loading' | 'authenticated' | 'anonymous';

interface AuthContextValue {
  status: Status;
  user: User | null;
  /** Papel com que o usuário está navegando (escolhido no seletor "Ver como"). */
  activeRole: Role | null;
  setActiveRole: (role: Role) => void;
  login: (email: string, password: string) => Promise<User>;
  register: (name: string, email: string, password: string) => Promise<User>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

// Preferência só deste navegador; se o storage estiver bloqueado, segue sem ela
function readStoredRole(): string | null {
  try {
    return localStorage.getItem(ACTIVE_ROLE_KEY);
  } catch {
    return null;
  }
}

function storeRole(role: Role) {
  try {
    localStorage.setItem(ACTIVE_ROLE_KEY, role);
  } catch {
    // ignora
  }
}

function pickActiveRole(user: User, preferred: string | null): Role {
  return user.roles.find((role) => role === preferred) ?? user.roles[0];
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [status, setStatus] = useState<Status>('loading');
  const [user, setUser] = useState<User | null>(null);
  const [activeRole, setActiveRoleState] = useState<Role | null>(null);
  // Dados em cache (TCCs, avisos) são de quem estava logado: não passam para o próximo usuário
  const queryClient = useQueryClient();

  const startSession = useCallback((data: AuthResponse) => {
    queryClient.clear();
    session.setToken(data.accessToken);
    setUser(data.user);
    setActiveRoleState(pickActiveRole(data.user, readStoredRole()));
    setStatus('authenticated');
    return data.user;
  }, [queryClient]);

  const endSession = useCallback(() => {
    queryClient.clear();
    session.setToken(null);
    setUser(null);
    setActiveRoleState(null);
    setStatus('anonymous');
  }, [queryClient]);

  // Ao abrir o app, tenta restaurar a sessão pelo cookie de refresh
  useEffect(() => {
    let cancelled = false;
    refreshSession()
      .then((data) => !cancelled && startSession(data))
      .catch(() => !cancelled && endSession());
    return () => {
      cancelled = true;
    };
  }, [startSession, endSession]);

  // Refresh recusado no meio do uso (cookie expirou ou foi revogado)
  useEffect(() => session.onExpired(endSession), [endSession]);

  const value = useMemo<AuthContextValue>(
    () => ({
      status,
      user,
      activeRole,
      setActiveRole: (role) => {
        if (!user?.roles.includes(role)) return;
        storeRole(role);
        setActiveRoleState(role);
      },
      login: async (email, password) => startSession((await api.post<AuthResponse>('/auth/login', { email, password })).data),
      register: async (name, email, password) =>
        startSession((await api.post<AuthResponse>('/auth/register', { name, email, password })).data),
      logout: async () => {
        try {
          await api.post('/auth/logout');
        } finally {
          endSession();
        }
      },
    }),
    [status, user, activeRole, startSession, endSession],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth deve ser usado dentro de AuthProvider');
  return ctx;
}
