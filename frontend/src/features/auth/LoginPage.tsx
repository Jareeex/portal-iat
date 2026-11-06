import { useState, type FormEvent } from 'react';
import { Navigate, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { errorMessage } from '../../utils/error';
import { Alert, Loader } from '../../components/common/Feedback';

export default function LoginPage() {
  const { user, loading, login } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const from = (location.state as { from?: string } | null)?.from ?? '/';

  if (loading) return <Loader fullPage />;
  if (user) return <Navigate to={from} replace />;

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      await login(email.trim(), password);
      navigate(from, { replace: true });
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="auth-page">
      <section className="login-story" aria-label="Portal IAT">
        <div className="login-story-copy">
          <span className="eyebrow">Indonesia Air Transport Internal Acces</span>
          <h1>Connected Workplace.</h1>
          <p>Your central access to internal systems, information, and services. Stay connected, stay informed, and work seamlessly.</p>
        </div>
        <div className="login-story-foot">
          <span className="status-dot" />
          <span>Portal internal IAT</span>
        </div>
      </section>

      <form className="login-card" onSubmit={onSubmit}>
        <div className="login-head">
          <img className="login-logo-full" src="/Logo-IAT.svg" alt="Indonesia Air Transport" />
          <h1>Welcome</h1>
          <p className="muted">Login to Your Account</p>
        </div>

        {error && <Alert>{error}</Alert>}

        <label className="field">
          <span className="field-label">Email</span>
          <input
            type="email"
            autoComplete="username"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
            autoFocus
          />
        </label>

        <label className="field">
          <span className="field-label">Password</span>
          <input
            type="password"
            autoComplete="current-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />
        </label>

        <button type="submit" className="btn btn-primary btn-block" disabled={submitting}>
          {submitting ? 'Memproses...' : 'Masuk'}
        </button>
      </form>
    </div>
  );
}
