import { useMemo, useState } from 'react';
import { Outlet } from 'react-router-dom';
import { Sidebar, type NavItem } from '../components/sidebar/Sidebar';
import { Navbar } from '../components/navbar/Navbar';
import { useAuth } from '../hooks/useAuth';
import { usePermission } from '../hooks/usePermission';
import { TicketReminder } from '../features/it-tickets/TicketReminder';

export default function UserLayout() {
    const { pages, user } = useAuth();
    const { isMaster } = usePermission();
    const [open, setOpen] = useState(false);
    const [unassignedTickets, setUnassignedTickets] = useState(0);
    const isItStaff = isMaster || user?.department?.code === 'IT';

    const items = useMemo<NavItem[]>(
        () => [
            { to: '/dashboard', label: 'Dashboard', icon: 'layout-dashboard' },
            { to: '/leave-request', label: 'Leave Request', icon: 'calendar' },
            {
                to: '/it/tickets',
                label: 'IT Tickets',
                icon: 'ticket',
                ...(isItStaff ? { badge: unassignedTickets } : {}),
            },
            ...pages
                .filter(
                    (p) => p.route !== '/dashboard' && p.key !== 'it-tickets',
                )
                .map((p) => ({
                    to: p.route,
                    label: p.name,
                    icon: p.icon ?? p.key,
                })),
        ],
        [isItStaff, pages, unassignedTickets],
    );

    return (
        <div className="shell">
            <Sidebar
                title="Portal IAT"
                items={items}
                footerLink={
                    isMaster
                        ? { to: '/master/users', label: 'Master Panel' }
                        : undefined
                }
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
            <TicketReminder
                enabled={isItStaff}
                onCountChange={setUnassignedTickets}
            />
        </div>
    );
}
