import type { ReactNode } from 'react';

export function Loader({
    text = 'Memuat...',
    fullPage = false,
}: {
    text?: string;
    fullPage?: boolean;
}) {
    return (
        <div className={fullPage ? 'loader loader-full' : 'loader'}>
            <span className="spinner" aria-hidden="true" />
            <span>{text}</span>
        </div>
    );
}

export function Alert({
    kind = 'error',
    children,
}: {
    kind?: 'error' | 'success' | 'info';
    children: ReactNode;
}) {
    return (
        <div
            className={`alert alert-${kind}`}
            role={kind === 'error' ? 'alert' : 'status'}
        >
            {children}
        </div>
    );
}

export function EmptyState({ children }: { children: ReactNode }) {
    return <div className="empty">{children}</div>;
}
