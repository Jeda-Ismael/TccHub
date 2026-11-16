import { FormEvent, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { api } from '../../api/client';
import { useAuth } from '../../auth/AuthContext';
import { GraduationCapIcon } from '../../components/icons/GraduationCapIcon';

export function Register() {
  const [name, setName] = useState('');
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
      const { data } = await api.post('/auth/register', { name, email, password });
      login(data.accessToken);
      navigate('/');
    } catch (err: any) {
      setError(err.response?.data?.message || 'Não foi possível criar a conta');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="auth-page">
      <div className="auth-brand">
        <span className="sidebar-brand-mark">
          <GraduationCapIcon size={20} />
        </span>
        hubtcc
      </div>
      <div className="card auth-card">
        <h1>Criar conta</h1>
        <p className="auth-sub">Todo novo cadastro começa com o papel de Aluno.</p>
        <form onSubmit={handleSubmit}>
          <div className="form-group">
            <label htmlFor="reg-name">Nome</label>
            <input id="reg-name" autoComplete="name" value={name} onChange={(e) => setName(e.target.value)} required />
          </div>
          <div className="form-group">
            <label htmlFor="reg-email">Email</label>
            <input id="reg-email" type="email" autoComplete="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
          </div>
          <div className="form-group">
            <label htmlFor="reg-password">Senha</label>
            <input
              id="reg-password"
              type="password"
              autoComplete="new-password"
              minLength={6}
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
            />
            <span className="field-hint">Mínimo de 6 caracteres.</span>
          </div>
          {error && <p className="error-text">{error}</p>}
          <button type="submit" className="block" disabled={loading}>
            {loading ? 'Criando...' : 'Criar conta'}
          </button>
        </form>
        <p className="auth-alt">
          Já tem conta? <Link to="/login">Entrar</Link>
        </p>
      </div>
    </div>
  );
}
