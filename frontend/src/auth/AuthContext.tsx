import { createContext, useContext, useMemo, useState, ReactNode } from 'react';
import { jwtDecode } from './jwt';
import { Role } from '../api/types';

interface AuthUser {
  id: string;
  email: string;
  role: Role;
}

interface AuthContextValue {
  user: AuthUser | null;
  token: string | null;
  login: (token: string) => void;
  logout: () => void;
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

function loadUserFromToken(token: string | null): AuthUser | null {
  if (!token) return null;
  try {
    const payload = jwtDecode<{ sub: string; email: string; role: Role; exp: number }>(token);
    if (payload.exp * 1000 < Date.now()) return null;
    return { id: payload.sub, email: payload.email, role: payload.role };
  } catch {
    return null;
  }
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [token, setToken] = useState<string | null>(() => localStorage.getItem('hubtcc_token'));
  const user = useMemo(() => loadUserFromToken(token), [token]);

  const login = (newToken: string) => {
    localStorage.setItem('hubtcc_token', newToken);
    setToken(newToken);
  };

  const logout = () => {
    localStorage.removeItem('hubtcc_token');
    setToken(null);
  };

  return <AuthContext.Provider value={{ user, token, login, logout }}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth deve ser usado dentro de AuthProvider');
  return ctx;
}
