import { FormEvent, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { api } from '../../api/client';
import { useAuth } from '../../auth/AuthContext';
import { WebGLSwitchPanel } from '../../components/WebGLSwitchPanel';

export function Login() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const { login } = useAuth();
  const navigate = useNavigate();

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      const { data } = await api.post('/auth/login', { email, password });
      login(data.accessToken);
      navigate('/');
    } catch (err: any) {
      setError(err.response?.data?.message || 'Não foi possível entrar');
    } finally {
      setLoading(false);
    }
  }

  const apiUrl = import.meta.env.VITE_API_URL || 'http://localhost:3000';

  return (
    <div className="login-split">
      <aside className="login-brand">
        <WebGLSwitchPanel />
      </aside>

      <div className="login-form-side">
        <div className="card login-form-card">
          <h2>Entrar</h2>
          <p className="login-form-subtitle">Acesse com sua conta institucional.</p>
          <form onSubmit={handleSubmit}>
            <div className="form-group">
              <label>Email</label>
              <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
            </div>
            <div className="form-group">
              <label>Senha</label>
              <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required />
            </div>
            {error && <p className="error-text">{error}</p>}
            <button type="submit" disabled={loading} style={{ width: '100%' }}>
              {loading ? 'Entrando...' : 'Entrar'}
            </button>
          </form>
          <div style={{ margin: '1rem 0', textAlign: 'center' }} className="muted">
            ou
          </div>
          <a href={`${apiUrl}/auth/google`}>
            <button type="button" className="secondary" style={{ width: '100%' }}>
              Entrar com Google
            </button>
          </a>
          <p className="muted" style={{ marginTop: '1rem' }}>
            Não tem conta? <Link to="/register">Cadastre-se</Link>
          </p>
        </div>
      </div>
    </div>
  );
}
