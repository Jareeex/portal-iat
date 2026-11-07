import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { portalService } from '../../services/portal.service';
import { errorMessage } from '../../utils/error';
import { Alert, EmptyState, Loader } from '../../components/common/Feedback';
import { useAuth } from '../../hooks/useAuth';
import type { PortalPage } from '../../types';
import { NavIcon } from '../../components/common/NavIcon';

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${value}T00:00:00`));
}

export default function DashboardPage() {
    const { refresh } = useAuth();
    const [greeting, setGreeting] = useState('');
    const [pages, setPages] = useState<PortalPage[]>([]);
    const [expiredTrainings, setExpiredTrainings] = useState<
        { name: string; next_check: string }[]
    >([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        portalService
            .dashboard()
            .then((d) => {
                setGreeting(d.greeting);
                setPages(d.pages.filter((p) => p.route !== '/dashboard'));
                setExpiredTrainings(d.expired_trainings);
                void refresh().catch((e) => setError(errorMessage(e)));
            })
            .catch((e) => setError(errorMessage(e)))
            .finally(() => setLoading(false));
    }, [refresh]);

    if (loading) return <Loader />;

    return (
        <div>
            <div className="dashboard-hero">
                <div className="dashboard-hero-copy">
                    <span className="dashboard-eyebrow">
                        INDONESIA AIR TRANSPORT <span>·</span> PORTAL
                        OPERASIONAL
                    </span>
                    <h2>{greeting || 'Dashboard'}</h2>
                    <p>
                        Satu pintu untuk mengakses layanan dan informasi kerja
                        Anda.
                    </p>
                </div>
                <svg
                    className="dashboard-route"
                    viewBox="0 0 420 180"
                    fill="none"
                    aria-hidden="true"
                >
                    <path
                        className="route-line"
                        d="M21 142C91 125 86 55 174 72C252 88 258 26 359 23"
                    />
                    <circle cx="21" cy="142" r="4" />
                    <circle cx="174" cy="72" r="4" />
                    <path
                        className="route-plane"
                        d="m347 21 29-8-12 28-5-14-12-6Z"
                    />
                </svg>
            </div>

            {error && <Alert>{error}</Alert>}

            {expiredTrainings.length > 0 && (
                <Alert>
                    <strong>Pelatihan kedaluwarsa</strong>
                    <ul>
                        {expiredTrainings.map((training, index) => (
                            <li
                                key={`${training.name}-${training.next_check}-${index}`}
                            >
                                {training.name} — expired{' '}
                                {formatDate(training.next_check)}
                            </li>
                        ))}
                    </ul>
                </Alert>
            )}

            {pages.length === 0 && !error ? (
                <EmptyState>
                    Belum ada fitur untukmu. Hubungi master untuk minta akses.
                </EmptyState>
            ) : (
                <div className="card-grid">
                    {pages.map((p) => (
                        <Link key={p.id} to={p.route} className="feature-card">
                            <span className="feature-icon">
                                <NavIcon name={p.icon ?? p.key} />
                            </span>
                            <strong>{p.name}</strong>
                            <span className="muted">
                                {p.description ?? p.route}
                            </span>
                        </Link>
                    ))}
                </div>
            )}
        </div>
    );
}
