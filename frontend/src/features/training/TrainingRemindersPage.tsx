import { useCallback, useEffect, useState } from 'react';
import { Alert, EmptyState, Loader } from '../../components/common/Feedback';
import { DataTable, type Column } from '../../components/tables/DataTable';
import {
    trainingReminderService,
    type TrainingReminderRecipient,
} from '../../services/training-reminder.service';
import { errorMessage } from '../../utils/error';

export default function TrainingRemindersPage() {
    const [recipients, setRecipients] = useState<TrainingReminderRecipient[]>(
        [],
    );
    const [loading, setLoading] = useState(true);
    const [savingRecipient, setSavingRecipient] = useState<number | null>(null);
    const [sending, setSending] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [message, setMessage] = useState<string | null>(null);

    const loadRecipients = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            setRecipients(await trainingReminderService.recipients());
        } catch (cause) {
            setError(errorMessage(cause));
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        void loadRecipients();
    }, [loadRecipients]);

    async function setRecipientEnabled(
        recipient: TrainingReminderRecipient,
        enabled: boolean,
    ) {
        setSavingRecipient(recipient.id);
        setError(null);
        setMessage(null);

        try {
            const updated = await trainingReminderService.setRecipientEnabled(
                recipient.id,
                enabled,
            );
            setRecipients((current) =>
                current.map((item) =>
                    item.id === updated.id
                        ? {
                              ...item,
                              training_reminder_enabled:
                                  updated.training_reminder_enabled,
                          }
                        : item,
                ),
            );
        } catch (cause) {
            setError(errorMessage(cause));
        } finally {
            setSavingRecipient(null);
        }
    }

    async function sendAll() {
        if (
            !window.confirm(
                'Kirim reminder untuk semua training yang memiliki tanggal next check kepada penerima yang aktif?',
            )
        ) {
            return;
        }

        setSending(true);
        setError(null);
        setMessage(null);
        try {
            const response = await trainingReminderService.sendAll();
            setMessage(response.message);
        } catch (cause) {
            setError(errorMessage(cause));
        } finally {
            setSending(false);
        }
    }

    const columns: Column<TrainingReminderRecipient>[] = [
        {
            header: 'Penerima',
            render: (recipient) => (
                <div>
                    <strong>{recipient.name}</strong>
                    <div className="muted">{recipient.email}</div>
                </div>
            ),
        },
        {
            header: 'Role',
            render: (recipient) => (
                <span
                    className={
                        recipient.role === 'master'
                            ? 'badge badge-accent'
                            : 'badge'
                    }
                >
                    {recipient.role}
                </span>
            ),
        },
        {
            header: 'Reminder training',
            render: (recipient) => (
                <label className="check">
                    <input
                        type="checkbox"
                        checked={recipient.training_reminder_enabled}
                        disabled={savingRecipient !== null}
                        aria-label={`Aktifkan reminder training untuk ${recipient.name}`}
                        onChange={(event) =>
                            void setRecipientEnabled(
                                recipient,
                                event.target.checked,
                            )
                        }
                    />
                    {recipient.training_reminder_enabled
                        ? 'Aktif'
                        : 'Nonaktif'}
                </label>
            ),
        },
    ];

    return (
        <div>
            <div className="page-head page-head-row">
                <div>
                    <h2>Reminder Training</h2>
                    <p className="muted">
                        Atur penerima reminder otomatis dan kirim semua training
                        secara manual. Kirim semua mencakup training yang
                        memiliki tanggal next check, termasuk yang belum dekat
                        jatuh tempo.
                    </p>
                </div>
                <button
                    type="button"
                    className="btn btn-primary"
                    onClick={() => void sendAll()}
                    disabled={sending || loading || savingRecipient !== null}
                >
                    {sending ? 'Mengirim...' : 'Kirim semua'}
                </button>
            </div>

            {error && <Alert>{error}</Alert>}
            {message && <Alert kind="success">{message}</Alert>}

            {loading ? (
                <Loader />
            ) : recipients.length === 0 ? (
                <EmptyState>
                    Belum ada user yang memenuhi syarat sebagai penerima
                    reminder.
                </EmptyState>
            ) : (
                <DataTable
                    columns={columns}
                    rows={recipients}
                    rowKey={(recipient) => recipient.id}
                    empty="Belum ada penerima reminder."
                />
            )}
        </div>
    );
}
