import { render } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ReactNode } from 'react';
import { AuthProvider } from '../auth/AuthContext';

/**
 * Renderiza uma tela com React Query e rotas. `route` é o padrão da rota
 * (ex.: "/advisor/:id") e `path` o endereço aberto. Com `auth`, envolve no
 * AuthProvider (a sessão vem do mock de /auth/refresh).
 */
export function renderPage(ui: ReactNode, { path = '/', route = '/', auth = false }: { path?: string; route?: string; auth?: boolean } = {}) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  const tree = (
    <MemoryRouter initialEntries={[path]}>
      <Routes>
        <Route path={route} element={ui} />
        <Route path="*" element={<p>Outra tela</p>} />
      </Routes>
    </MemoryRouter>
  );

  return render(<QueryClientProvider client={client}>{auth ? <AuthProvider>{tree}</AuthProvider> : tree}</QueryClientProvider>);
}
