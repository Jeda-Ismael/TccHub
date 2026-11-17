import { ReactNode } from 'react';
import { BrowserRouter, Route, Routes } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { AuthProvider, useAuth } from './auth/AuthContext';
import { ProtectedRoute } from './auth/ProtectedRoute';
import { Layout } from './components/Layout';
import { Toaster } from './components/Toaster';
import { Login } from './pages/auth/Login';
import { Register } from './pages/auth/Register';
import { ForgotPassword } from './pages/auth/ForgotPassword';
import { ResetPassword } from './pages/auth/ResetPassword';
import { Dashboard } from './pages/Dashboard';
import { StudentHome } from './pages/student/StudentHome';
import { AdvisorHome } from './pages/advisor/AdvisorHome';
import { AdvisorTccDetail } from './pages/advisor/AdvisorTccDetail';
import { EvaluatorHome } from './pages/evaluator/EvaluatorHome';
import { EvaluatorTccDetail } from './pages/evaluator/EvaluatorTccDetail';
import { CoordinationLayout } from './pages/coordination/CoordinationLayout';
import { TccsOverview } from './pages/coordination/TccsOverview';
import { CoordinationTccDetail } from './pages/coordination/CoordinationTccDetail';
import { UsersManagement } from './pages/coordination/UsersManagement';
import { Catalogs } from './pages/coordination/Catalogs';
import { Periods } from './pages/coordination/Periods';
import { Settings } from './pages/coordination/Settings';
import { RepositorySearch } from './pages/repository/RepositorySearch';
import { RepositoryDetail } from './pages/repository/RepositoryDetail';

const queryClient = new QueryClient();

/** Enquanto a sessão é restaurada pelo cookie, não mostra nada que dependa do login. */
function SessionGate({ children }: { children: ReactNode }) {
  const { status } = useAuth();
  if (status === 'loading') {
    return <div className="auth-loading">Carregando...</div>;
  }
  return <>{children}</>;
}

export function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <BrowserRouter>
          <SessionGate>
          <Routes>
            <Route path="/login" element={<Login />} />
            <Route path="/register" element={<Register />} />
            <Route path="/forgot-password" element={<ForgotPassword />} />
            <Route path="/reset-password" element={<ResetPassword />} />

            <Route element={<Layout />}>
              <Route element={<ProtectedRoute />}>
                <Route path="/" element={<Dashboard />} />
                {/* Repositório: qualquer pessoa logada */}
                <Route path="/repository" element={<RepositorySearch />} />
                <Route path="/repository/:id" element={<RepositoryDetail />} />
              </Route>

              <Route element={<ProtectedRoute allow={['STUDENT']} />}>
                <Route path="/student" element={<StudentHome />} />
              </Route>

              <Route element={<ProtectedRoute allow={['ADVISOR']} />}>
                <Route path="/advisor" element={<AdvisorHome />} />
                <Route path="/advisor/:id" element={<AdvisorTccDetail />} />
              </Route>

              <Route element={<ProtectedRoute allow={['EVALUATOR']} />}>
                <Route path="/evaluator" element={<EvaluatorHome />} />
                <Route path="/evaluator/:id" element={<EvaluatorTccDetail />} />
              </Route>

              <Route element={<ProtectedRoute allow={['COORDINATION']} />}>
                <Route path="/coordination" element={<CoordinationLayout />}>
                  <Route index element={<TccsOverview />} />
                  <Route path="tcc/:id" element={<CoordinationTccDetail />} />
                  <Route path="users" element={<UsersManagement />} />
                  <Route path="catalogs" element={<Catalogs />} />
                  <Route path="periods" element={<Periods />} />
                  <Route path="settings" element={<Settings />} />
                </Route>
              </Route>
            </Route>
          </Routes>
          </SessionGate>
        </BrowserRouter>
        <Toaster />
      </AuthProvider>
    </QueryClientProvider>
  );
}
