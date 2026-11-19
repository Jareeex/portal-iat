import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { portalService } from '../../services/portal.service';
import { errorMessage } from '../../utils/error';
import { Alert, Loader } from '../../components/common/Feedback';
import { useAuth } from '../../hooks/useAuth';
import type { PortalPage } from '../../types';
import { NavIcon } from '../../components/common/NavIcon';

export default function DashboardPage() {
    const { refresh } = useAuth();
    const [greeting, setGreeting] = useState('');
    const [pages, setPages] = useState<PortalPage[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        portalService
            .dashboard()
            .then((d) => {
                setGreeting(d.greeting);
                setPages(
                    d.pages.filter(
                        (p) =>
                            p.route !== '/dashboard' &&
                            p.route !== '/attendance' &&
                            p.route !== '/it/tickets',
                    ),
                );
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

            <div className="card-grid">
                <Link to="/it/tickets" className="feature-card">
                    <span className="feature-icon">
                        <NavIcon name="ticket" />
                    </span>
                    <strong>IT Tickets</strong>
                    <span className="muted">
                        Buat dan pantau permintaan dukungan IT.
                    </span>
                </Link>
                <Link to="/attendance" className="feature-card">
                    <span className="feature-icon">
                        <NavIcon name="clock" />
                    </span>
                    <strong>Attendances</strong>
                    <span className="muted">
                        Lihat riwayat kehadiran Anda.
                    </span>
                </Link>
                {pages.map((p) => (
                    <Link
                        key={p.id}
                        to={
                            p.name.trim().toLowerCase() === 'leave' ||
                            p.key.trim().toLowerCase() === 'leave' ||
                            p.route.trim().toLowerCase() === '/leave'
                                ? '/leave-request'
                                : p.route
                        }
                        className="feature-card"
                    >
                        <span className="feature-icon">
                            <NavIcon name={p.icon ?? p.key} />
                        </span>
                        <strong>
                            {p.name.trim().toLowerCase() === 'leave'
                                ? 'Leave Request'
                                : p.name}
                        </strong>
                        <span className="muted">
                            {p.description ?? p.route}
                        </span>
                    </Link>
                ))}
            </div>
        </div>
    );
}
