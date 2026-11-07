import { useMemo, useState } from 'react';
import { Outlet } from 'react-router-dom';
import { Sidebar, type NavItem } from '../components/sidebar/Sidebar';
import { Navbar } from '../components/navbar/Navbar';
import { useAuth } from '../hooks/useAuth';
import { usePermission } from '../hooks/usePermission';

export default function UserLayout() {
  const { pages } = useAuth();
  const { isMaster } = usePermission();
  const [open, setOpen] = useState(false);

  const items = useMemo<NavItem[]>(
    () => [
      { to: '/dashboard', label: 'Dashboard', icon: 'layout-dashboard' },
      ...pages
        .filter((p) => p.route !== '/dashboard')
        .map((p) => ({ to: p.route, label: p.name, icon: p.icon ?? p.key })),
    ],
    [pages],
  );

  return (
    <div className="shell">
      <Sidebar
        title="Portal IAT"
        items={items}
        footerLink={isMaster ? { to: '/master/users', label: 'Master Panel' } : undefined}
        open={open}
        onNavigate={() => setOpen(false)}
      />
      {open && <div className="scrim" onClick={() => setOpen(false)} />}
      <div className="main">
        <Navbar onMenu={() => setOpen((v) => !v)} />
        <main className="content">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
