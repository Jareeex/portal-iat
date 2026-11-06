import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { portalService } from '../../services/portal.service';
import { errorMessage } from '../../utils/error';
import { Alert, EmptyState, Loader } from '../../components/common/Feedback';
import { useAuth } from '../../hooks/useAuth';
import type { PortalPage } from '../../types';

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
        setPages(d.pages.filter((p) => p.route !== '/dashboard'));
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

      {pages.length === 0 && !error ? (
        <EmptyState>Belum ada fitur untukmu. Hubungi master untuk minta akses.</EmptyState>
      ) : (
        <div className="card-grid">
          {pages.map((p) => (
            <Link key={p.id} to={p.route} className="feature-card">
              <span className="nav-dot">{p.name.charAt(0).toUpperCase()}</span>
              <strong>{p.name}</strong>
              <span className="muted">{p.description ?? p.route}</span>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
