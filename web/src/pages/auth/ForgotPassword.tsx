import { FormEvent, useState } from 'react';
import { Link } from 'react-router-dom';
import { api, apiErrorMessage } from '../../api/client';
import { AuthCard } from './AuthCard';

/** Pede o link de troca de senha. A resposta é a mesma exista ou não a conta. */
export function ForgotPassword() {
  const [email, setEmail] = useState('');
  const [sent, setSent] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      await api.post('/auth/forgot-password', { email });
      setSent(true);
    } catch (err) {
      setError(apiErrorMessage(err, 'Não foi possível enviar o link. Tente novamente.'));
    } finally {
      setLoading(false);
    }
  }

  if (sent) {
    return (
      <AuthCard title="Confira seu e-mail" subtitle="Se o e-mail estiver cadastrado, você vai receber um link para trocar a senha.">
        <p className="auth-note">
          O link vale por 1 hora. Não chegou? Olhe a caixa de spam ou <button type="button" className="link-button" onClick={() => setSent(false)}>peça de novo</button>.
        </p>
        <p className="auth-footer">
          <Link to="/login">Voltar para o login</Link>
        </p>
      </AuthCard>
    );
  }

  return (
    <AuthCard title="Esqueci minha senha" subtitle="Informe o seu e-mail e enviaremos um link para criar uma senha nova.">
      <form onSubmit={handleSubmit}>
        <div className="form-group">
          <label htmlFor="forgot-email">E-mail</label>
          <input id="forgot-email" type="email" autoComplete="email" value={email} onChange={(e) => setEmail(e.target.value)} required autoFocus />
        </div>
        {error && (
          <p className="auth-error" role="alert">
            {error}
          </p>
        )}
        <button type="submit" className="auth-submit" disabled={loading}>
          {loading ? 'Enviando...' : 'Enviar link'}
        </button>
      </form>
      <p className="auth-footer">
        Lembrou a senha? <Link to="/login">Entrar</Link>
      </p>
    </AuthCard>
  );
}
