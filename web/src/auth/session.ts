// Token de acesso guardado só em memória: some ao fechar a aba e não fica
// exposto no localStorage. A sessão é restaurada pelo cookie de refresh
// (httpOnly, o JavaScript não lê) chamando /auth/refresh.

let accessToken: string | null = null;
const expiredListeners = new Set<() => void>();

export const session = {
  getToken: () => accessToken,
  setToken: (token: string | null) => {
    accessToken = token;
  },
  /** Avisa a aplicação que a sessão acabou (refresh recusado). */
  expire: () => {
    accessToken = null;
    expiredListeners.forEach((listener) => listener());
  },
  onExpired: (listener: () => void) => {
    expiredListeners.add(listener);
    return () => {
      expiredListeners.delete(listener);
    };
  },
};
