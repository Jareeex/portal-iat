import type { ReactNode } from 'react';

const icons: Record<string, ReactNode> = {
    'layout-dashboard': (
        <>
            <rect x="3" y="3" width="8" height="8" rx="1.5" />
            <rect x="13" y="3" width="8" height="5" rx="1.5" />
            <rect x="13" y="10" width="8" height="11" rx="1.5" />
            <rect x="3" y="13" width="8" height="8" rx="1.5" />
        </>
    ),
    dashboard: (
        <>
            <rect x="3" y="3" width="8" height="8" rx="1.5" />
            <rect x="13" y="3" width="8" height="5" rx="1.5" />
            <rect x="13" y="10" width="8" height="11" rx="1.5" />
            <rect x="3" y="13" width="8" height="8" rx="1.5" />
        </>
    ),
    users: (
        <>
            <path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
            <circle cx="10" cy="7" r="4" />
            <path d="M20 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
        </>
    ),
    employees: (
        <>
            <path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
            <circle cx="10" cy="7" r="4" />
            <path d="M20 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
        </>
    ),
    calendar: (
        <>
            <rect x="3" y="5" width="18" height="16" rx="2" />
            <path d="M16 3v4M8 3v4M3 11h18" />
        </>
    ),
    'calendar-days': (
        <>
            <rect x="3" y="5" width="18" height="16" rx="2" />
            <path d="M16 3v4M8 3v4M3 11h18" />
            <path d="M8 15h.01M12 15h.01M16 15h.01M8 18h.01M12 18h.01" />
        </>
    ),
    leave: (
        <>
            <rect x="3" y="5" width="18" height="16" rx="2" />
            <path d="M16 3v4M8 3v4M3 11h18M9 16l2 2 4-4" />
        </>
    ),
    server: (
        <>
            <rect x="3" y="4" width="18" height="7" rx="2" />
            <rect x="3" y="13" width="18" height="7" rx="2" />
            <path d="M7 7.5h.01M7 16.5h.01M11 7.5h6M11 16.5h6" />
        </>
    ),
    plane: (
        <>
            <path d="m2 16 20-8-6 14-4-7-10 1z" />
            <path d="m12 15 4-6" />
        </>
    ),
    ticket: (
        <>
            <path d="M2 9a3 3 0 0 0 0 6v4a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-4a3 3 0 0 1 0-6V5a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2z" />
            <path d="M13 5v2M13 11v2M13 17v2" />
        </>
    ),
    clock: (
        <>
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5l3 2" />
        </>
    ),
    'file-text': (
        <>
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
            <path d="M14 2v6h6M8 13h8M8 17h8" />
        </>
    ),
    reports: (
        <>
            <path d="M3 3v18h18" />
            <path d="m19 9-5 5-4-4-5 5" />
        </>
    ),
    'graduation-cap': (
        <>
            <path d="m2 10 10-5 10 5-10 5z" />
            <path d="M6 12v5c3 2 9 2 12 0v-5M22 10v6" />
        </>
    ),
    building: (
        <>
            <rect x="4" y="3" width="16" height="18" rx="2" />
            <path d="M9 21v-4h6v4M8 7h1M15 7h1M8 11h1M15 11h1" />
        </>
    ),
    'building-2': (
        <>
            <path d="M3 21h18M5 21V5l7-3v19M19 21V9l-7-4" />
            <path d="M9 9v.01M9 13v.01M9 17v.01M15 13v.01M15 17v.01" />
        </>
    ),
    panels: (
        <>
            <rect x="3" y="4" width="18" height="16" rx="2" />
            <path d="M9 4v16M3 9h6" />
        </>
    ),
    shield: (
        <>
            <path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11" />
            <path d="m9 12 2 2 4-4" />
        </>
    ),
    'arrow-left': (
        <>
            <path d="m12 19-7-7 7-7M19 12H5" />
        </>
    ),
    'layout-grid': (
        <>
            <rect x="3" y="3" width="8" height="8" rx="2" />
            <rect x="13" y="3" width="8" height="8" rx="2" />
            <rect x="3" y="13" width="8" height="8" rx="2" />
            <rect x="13" y="13" width="8" height="8" rx="2" />
        </>
    ),
};

const aliases: Record<string, string> = {
    access: 'shield',
    'master panel': 'building',
    pages: 'panels',
    'back to portal': 'arrow-left',
    'kembali ke portal': 'arrow-left',
};

export function NavIcon({
    name,
    className,
}: {
    name: string;
    className?: string;
}) {
    const key = name.trim().toLowerCase();
    const icon = icons[key] ?? icons[aliases[key]] ?? icons['layout-grid'];

    return (
        <svg
            className={className ? `nav-icon ${className}` : 'nav-icon'}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.7"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            focusable="false"
        >
            {icon}
        </svg>
    );
}
