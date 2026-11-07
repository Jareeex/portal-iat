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
                <svg
                    className="login-flight-route"
                    viewBox="0 0 520 360"
                    fill="none"
                    aria-hidden="true"
                >
                    <path d="M56 310C151 279 119 188 249 182C349 177 325 92 464 48" />
                    <circle cx="56" cy="310" r="5" />
                    <circle cx="249" cy="182" r="5" />
                    <path d="m451 44 24-8-10 23-3-12-11-3Z" />
                </svg>
                <div className="login-story-copy">
                    <span className="eyebrow">
                        INDONESIA AIR TRANSPORT · INTERNAL
                    </span>
                    <h1>
                        Terhubung.
                        <br />
                        Siap beroperasi.
                    </h1>
                    <p>
                        Satu akses terpadu untuk sistem, informasi, dan layanan
                        kerja Indonesia Air Transport.
                    </p>
                </div>
                <div className="login-story-foot">
                    <span className="status-dot" />
                    <span>Portal internal IAT</span>
                </div>
            </section>

            <form className="login-card" onSubmit={onSubmit}>
                <div className="login-head">
                    <img
                        className="login-logo-full"
                        src="/Logo-IAT.svg"
                        alt="Indonesia Air Transport"
                    />
                    <span className="login-section-label">
                        RUANG KERJA DIGITAL
                    </span>
                    <h1>Selamat datang</h1>
                    <p className="muted">Masuk dengan akun kerja Anda.</p>
                </div>

                {error && <Alert>{error}</Alert>}

                <label className="field">
                    <span className="field-label">Email kerja</span>
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
                    <span className="field-label">Kata sandi</span>
                    <input
                        type="password"
                        autoComplete="current-password"
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                        required
                    />
                </label>

                <button
                    type="submit"
                    className="btn btn-primary btn-block"
                    disabled={submitting}
                >
                    {submitting ? 'Memverifikasi akses...' : 'Masuk ke portal'}
                </button>
            </form>
        </div>
    );
}
