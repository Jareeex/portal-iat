import { useState } from 'react';
import { Outlet } from 'react-router-dom';
import { Sidebar, type NavItem } from '../components/sidebar/Sidebar';
import { Navbar } from '../components/navbar/Navbar';

const items: NavItem[] = [
    { to: '/master/users', label: 'Users', icon: 'users' },
    { to: '/master/departments', label: 'Departments', icon: 'building-2' },
    { to: '/master/pages', label: 'Pages', icon: 'panels' },
    { to: '/master/access', label: 'Access', icon: 'shield' },
];

export default function MasterLayout() {
    const [open, setOpen] = useState(false);

    return (
        <div className="shell">
            <Sidebar
                title="Master Panel"
                items={items}
                footerLink={{
                    to: '/dashboard',
                    label: 'Kembali ke Portal',
                    icon: 'arrow-left',
                }}
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
