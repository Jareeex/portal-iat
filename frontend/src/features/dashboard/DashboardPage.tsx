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
  const [expiredTrainings, setExpiredTrainings] = useState<{ name: string; next_check: string }[]>([]);
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
      <div className="page-head dashboard-head">
        <img className="dashboard-mark" src="/logo-only.svg" alt="" />
        <div>
          <h2>{greeting || 'Dashboard'}</h2>
          <p className="muted">Fitur yang bisa kamu akses:</p>
        </div>
      </div>

      {error && <Alert>{error}</Alert>}

      {expiredTrainings.length > 0 && (
        <Alert>
          <strong>Training expired</strong>
          <ul>
            {expiredTrainings.map((training, index) => (
              <li key={`${training.name}-${training.next_check}-${index}`}>
                {training.name} — expired {formatDate(training.next_check)}
              </li>
            ))}
          </ul>
        </Alert>
      )}

      {pages.length === 0 && !error ? (
        <EmptyState>Belum ada fitur untukmu. Hubungi master untuk minta akses.</EmptyState>
      ) : (
        <div className="card-grid">
          {pages.map((p) => (
            <Link key={p.id} to={p.route} className="feature-card">
              <span className="feature-icon"><NavIcon name={p.icon ?? p.key} /></span>
              <strong>{p.name}</strong>
              <span className="muted">{p.description ?? p.route}</span>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
