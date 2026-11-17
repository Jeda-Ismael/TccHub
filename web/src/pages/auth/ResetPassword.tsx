import { FormEvent, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { apiErrorMessage } from '../../api/client';
import { useAuth } from '../../auth/AuthContext';
import { AuthCard } from './AuthCard';

/** Página aberta pelo link do e-mail: escolhe a senha nova e já entra. */
export function ResetPassword() {
  const [params] = useSearchParams();
  const token = params.get('token') ?? '';
  const [password, setPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const { resetPassword } = useAuth();
  const navigate = useNavigate();

  if (!token) {
    return (
      <AuthCard title="Link incompleto" subtitle="Abra o link exatamente como chegou no e-mail, ou peça um novo.">
        <p className="auth-footer">
          <Link to="/forgot-password">Pedir um novo link</Link>
        </p>
      </AuthCard>
    );
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    if (password !== confirm) {
      setError('As duas senhas não são iguais.');
      return;
    }
    setLoading(true);
    try {
      await resetPassword(token, password);
      navigate('/', { replace: true });
    } catch (err) {
      setError(apiErrorMessage(err, 'Não foi possível trocar a senha.'));
      setLoading(false);
    }
  }

  return (
    <AuthCard title="Nova senha" subtitle="Escolha uma senha com pelo menos 8 caracteres.">
      <form onSubmit={handleSubmit}>
        <div className="form-group">
          <label htmlFor="reset-password">Nova senha</label>
          <input
            id="reset-password"
            type="password"
            autoComplete="new-password"
            minLength={8}
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
            autoFocus
          />
        </div>
        <div className="form-group">
          <label htmlFor="reset-confirm">Repita a senha</label>
          <input id="reset-confirm" type="password" autoComplete="new-password" minLength={8} value={confirm} onChange={(e) => setConfirm(e.target.value)} required />
        </div>
        {error && (
          <p className="auth-error" role="alert">
            {error}
            {error.includes('expirou') && (
              <>
                {' '}
                <Link to="/forgot-password">Pedir um novo link</Link>
              </>
            )}
          </p>
        )}
        <button type="submit" className="auth-submit" disabled={loading}>
          {loading ? 'Salvando...' : 'Salvar e entrar'}
        </button>
      </form>
    </AuthCard>
  );
}
