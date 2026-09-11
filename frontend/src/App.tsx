import { BrowserRouter, Route, Routes } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { AuthProvider } from './auth/AuthContext';
import { ProtectedRoute } from './auth/ProtectedRoute';
import { Layout } from './components/Layout';
import { Login } from './pages/auth/Login';
import { Register } from './pages/auth/Register';
import { OAuthCallback } from './pages/auth/OAuthCallback';
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

const queryClient = new QueryClient();

export function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <BrowserRouter>
          <Routes>
            <Route path="/login" element={<Login />} />
            <Route path="/register" element={<Register />} />
            <Route path="/oauth-callback" element={<OAuthCallback />} />

            <Route element={<Layout />}>
              <Route element={<ProtectedRoute />}>
                <Route path="/" element={<Dashboard />} />
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
                </Route>
              </Route>
            </Route>
          </Routes>
        </BrowserRouter>
      </AuthProvider>
    </QueryClientProvider>
  );
}
