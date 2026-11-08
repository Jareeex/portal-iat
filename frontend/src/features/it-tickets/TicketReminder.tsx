import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { ticketService } from '../../services/ticket.service';
import { errorMessage } from '../../utils/error';
import type { TicketAlerts } from '../../types/ticket';

export function TicketReminder({
    enabled,
    onCountChange,
}: {
    enabled: boolean;
    onCountChange: (count: number) => void;
}) {
    const [notice, setNotice] = useState<
        TicketAlerts['new_tickets'][number] | null
    >(null);
    const [newTicketCount, setNewTicketCount] = useState(0);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!enabled) return;

        let mounted = true;
        let inFlight = false;
        let latestTicketId: number | undefined;

        async function poll(): Promise<void> {
            if (inFlight) return;
            inFlight = true;

            try {
                const alerts = await ticketService.alerts(latestTicketId);
                if (!mounted) return;

                if (
                    latestTicketId !== undefined &&
                    alerts.new_ticket_count > 0
                ) {
                    setNotice(alerts.new_tickets[0] ?? null);
                    setNewTicketCount(alerts.new_ticket_count);
                }

                latestTicketId = alerts.latest_ticket_id;
                onCountChange(alerts.unassigned_count);
                setError(null);
            } catch (pollError) {
                if (mounted) setError(errorMessage(pollError));
            } finally {
                inFlight = false;
            }
        }

        void poll();
        const interval = window.setInterval(() => {
            if (document.visibilityState === 'visible') void poll();
        }, 10_000);

        return () => {
            mounted = false;
            window.clearInterval(interval);
        };
    }, [enabled, onCountChange]);

    useEffect(() => {
        if (!notice) return;

        const timeout = window.setTimeout(() => {
            setNotice(null);
            setNewTicketCount(0);
        }, 12_000);

        return () => window.clearTimeout(timeout);
    }, [notice]);

    if (error) {
        return (
            <div className="ticket-reminder ticket-reminder-error" role="alert">
                Pengingat tiket gagal diperbarui: {error}
            </div>
        );
    }

    if (!notice) return null;

    return (
        <aside className="ticket-reminder" role="status">
            <div>
                <strong>
                    {newTicketCount > 1
                        ? `${newTicketCount} tiket IT baru`
                        : 'Tiket IT baru'}
                </strong>
                <p>
                    {notice.number} · {notice.title}
                </p>
            </div>
            <Link to={`/it/tickets/${notice.id}`}>Lihat tiket</Link>
            <button
                type="button"
                className="icon-btn"
                aria-label="Tutup pengingat"
                onClick={() => {
                    setNotice(null);
                    setNewTicketCount(0);
                }}
            >
                ×
            </button>
        </aside>
    );
}
