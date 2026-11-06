import { Link, useLocation } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { pageRegistry } from './registry';
import { EmptyState } from '../../components/common/Feedback';

/** Catch-all untuk halaman dinamis: cocokkan URL dengan halaman yang diizinkan backend. */
export default function PortalPage() {
  const { pathname } = useLocation();
  const { pages } = useAuth();

  const path = pathname.length > 1 ? pathname.replace(/\/+$/, '') : pathname;
  const page = pages.find((p) =>
    p.route === path || (p.key.trim().toLowerCase() === 'training' && path.startsWith(`${p.route}/`)),
  );

  if (!page) {
    return (
      <div>
        <div className="page-head">
          <h2>Halaman tidak tersedia</h2>
        </div>
        <EmptyState>
          Halaman ini tidak ada atau kamu tidak punya akses.
          <br />
          <Link to="/dashboard">Kembali ke Dashboard</Link>
        </EmptyState>
      </div>
    );
  }

  const Component = pageRegistry[page.key.trim().toLowerCase()];
  if (Component) return <Component />;

  return (
    <div>
      <div className="page-head">
        <h2>{page.name}</h2>
        {page.description && <p className="muted">{page.description}</p>}
      </div>
      <EmptyState>
        Halaman ini belum dibuat. Daftarkan komponennya di <code>features/portal/registry.tsx</code> dengan key{' '}
        <code>{page.key}</code>.
      </EmptyState>
    </div>
  );
}
