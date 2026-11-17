import { useMemo, useState } from 'react';
import { Outlet } from 'react-router-dom';
import {
    Sidebar,
    type NavEntry,
    type NavItem,
} from '../components/sidebar/Sidebar';
import { Navbar } from '../components/navbar/Navbar';
import { useAuth } from '../hooks/useAuth';
import { usePermission } from '../hooks/usePermission';
import { TicketReminder } from '../features/it-tickets/TicketReminder';

export default function UserLayout() {
    const { pages, user } = useAuth();
    const { isMaster, canEdit } = usePermission();
    const [open, setOpen] = useState(false);
    const [unassignedTickets, setUnassignedTickets] = useState(0);
    const isItStaff = isMaster || user?.department?.code === 'IT';
    const canManageHr = isMaster || canEdit('hr-employees');

    const items = useMemo<NavEntry[]>(() => {
        const availablePages = pages.filter(
            (page) =>
                page.route !== '/dashboard' &&
                page.key !== 'it-tickets' &&
                page.key.toLowerCase() !== 'leave' &&
                page.name.trim().toLowerCase() !== 'leave' &&
                page.route.trim().toLowerCase() !== '/leave',
        );
        const trainingPage = availablePages.find(
            (page) => page.key.trim().toLowerCase() === 'training',
        );
        const hrEmployeesPage = availablePages.find(
            (page) => page.key.trim().toLowerCase() === 'hr-employees',
        );
        const trainingProgramPage = availablePages.find((page) => {
            const key = page.key.trim().toLowerCase();
            const name = page.name.trim().toLowerCase();
            return key === 'training-program' || name === 'training program';
        });
        const trainingPages = new Set(
            [trainingPage, trainingProgramPage].filter(
                (page) => page !== undefined,
            ),
        );
        const menuItems: NavEntry[] = [
            { to: '/dashboard', label: 'Dashboard', icon: 'layout-dashboard' },
        ];

        if (canManageHr) {
            menuItems.push({
                label: 'HR',
                icon: 'users',
                children: [
                    ...(hrEmployeesPage
                        ? [
                              {
                                  to: hrEmployeesPage.route,
                                  label: 'Employee',
                                  icon: hrEmployeesPage.icon ?? 'employees',
                              },
                          ]
                        : []),
                    {
                        to: '/leave-request',
                        label: 'Leave Request',
                        icon: 'calendar',
                    },
                    {
                        to: '/attendance',
                        label: 'Attendance',
                        icon: 'clock',
                    },
                ],
            });
        } else {
            menuItems.push({
                to: '/leave-request',
                label: 'Leave Request',
                icon: 'calendar',
            });
        }

        menuItems.push({
            to: '/it/tickets',
            label: 'IT Tickets',
            icon: 'ticket',
            ...(isItStaff ? { badge: unassignedTickets } : {}),
        });

        const trainingChildren: NavItem[] = [];
        if (trainingPage || trainingProgramPage) {
            trainingChildren.push({
                to: trainingProgramPage?.route ?? '/training/program',
                label: 'Training Program',
                icon: trainingProgramPage?.icon ?? 'graduation-cap',
            });
        }
        if (trainingPage) {
            trainingChildren.push({
                to: trainingPage.route,
                label: 'Training Record',
                icon: trainingPage.icon ?? 'graduation-cap',
            });
        }
        if (trainingChildren.length > 0) {
            menuItems.push({
                label: 'Training',
                icon: 'graduation-cap',
                children: trainingChildren,
            });
        }

        menuItems.push(
            ...availablePages
                .filter(
                    (page) =>
                        !trainingPages.has(page) && page !== hrEmployeesPage,
                )
                .map((page) => ({
                    to: page.route,
                    label: page.name,
                    icon: page.icon ?? page.key,
                })),
        );

        return menuItems;
    }, [canManageHr, isItStaff, pages, unassignedTickets]);

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
