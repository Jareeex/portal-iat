import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { Link, useLocation, useParams } from 'react-router-dom';
import { Alert, Loader } from '../../components/common/Feedback';
import { Field } from '../../components/common/Field';
import {
    ticketService,
    type TicketUpdatePayload,
} from '../../services/ticket.service';
import { errorMessage } from '../../utils/error';
import {
    formatTicketDate,
    TICKET_PRIORITY_LABEL,
    TICKET_STATUS_LABEL,
    type TicketDetailResponse,
    type TicketPriority,
    type TicketStatus,
} from '../../types/ticket';

function AttachmentList({
    attachments,
    onDownload,
}: {
    attachments: TicketDetailResponse['ticket']['attachments'];
    onDownload: (id: number, name: string) => void;
}) {
    if (attachments.length === 0) return null;

    return (
        <ul className="ticket-attachments">
            {attachments.map((attachment) => (
                <li key={attachment.id}>
                    <button
                        type="button"
                        className="btn btn-ghost"
                        onClick={() =>
                            onDownload(attachment.id, attachment.name)
                        }
                    >
                        {attachment.name}
                    </button>
                </li>
            ))}
        </ul>
    );
}

export default function TicketDetailPage() {
    const { ticketId } = useParams();
    const location = useLocation();
    const id = Number(ticketId);
    const [result, setResult] = useState<TicketDetailResponse | null>(null);
    const [edit, setEdit] = useState<TicketUpdatePayload | null>(null);
    const [body, setBody] = useState('');
    const [isInternal, setIsInternal] = useState(false);
    const [files, setFiles] = useState<File[]>([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [success, setSuccess] = useState(
        (location.state as { message?: string } | null)?.message ?? null,
    );

    const loadTicket = useCallback(async () => {
        if (!Number.isInteger(id) || id < 1) {
            setError('Nomor tiket tidak valid.');
            setLoading(false);
            return;
        }

        setLoading(true);
        setError(null);
        try {
            const response = await ticketService.show(id);
            setResult(response);
            setEdit({
                status: response.ticket.status,
                priority: response.ticket.priority,
                category: response.ticket.category,
                assignee_id: response.ticket.assignee?.id ?? null,
                resolution: response.ticket.resolution ?? '',
            });
        } catch (loadError) {
            setError(errorMessage(loadError));
        } finally {
            setLoading(false);
        }
    }, [id]);

    useEffect(() => {
        void loadTicket();
    }, [loadTicket]);

    async function performAction(action: () => Promise<{ message: string }>) {
        setSaving(true);
        setError(null);
        setSuccess(null);
        try {
            const response = await action();
            setSuccess(response.message);
            await loadTicket();
        } catch (actionError) {
            setError(errorMessage(actionError));
        } finally {
            setSaving(false);
        }
    }

    async function saveTicket(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!edit) return;
        await performAction(() => ticketService.update(id, edit));
    }

    async function sendComment(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!body.trim()) return;

        const formData = new FormData();
        formData.append('body', body.trim());
        formData.append('is_internal', String(isInternal));
        files.forEach((file) => formData.append('files[]', file));

        setSaving(true);
        setError(null);
        setSuccess(null);
        try {
            const response = await ticketService.comment(id, formData);
            setSuccess(response.message);
            setBody('');
            setFiles([]);
            setIsInternal(false);
            await loadTicket();
        } catch (commentError) {
            setError(errorMessage(commentError));
        } finally {
            setSaving(false);
        }
    }

    async function downloadAttachment(attachmentId: number, name: string) {
        setError(null);
        try {
            const blob = await ticketService.attachment(attachmentId);
            const url = URL.createObjectURL(blob);
            const anchor = document.createElement('a');
            anchor.href = url;
            anchor.download = name;
            anchor.click();
            URL.revokeObjectURL(url);
        } catch (downloadError) {
            setError(errorMessage(downloadError));
        }
    }

    if (loading && !result) return <Loader text="Memuat tiket..." />;

    if (!result) {
        return (
            <section>
                <Link to="/it/tickets">← Kembali ke IT Tickets</Link>
                {error && <Alert>{error}</Alert>}
            </section>
        );
    }

    const { ticket, timeline, can, staff, categories } = result;

    return (
        <section>
            <header className="page-head">
                <Link to="/it/tickets">
                    ← {can.it ? 'IT Tickets' : 'Tiket saya'}
                </Link>
                <p className="ticket-number">{ticket.number}</p>
                <h2>{ticket.title}</h2>
                <div className="ticket-heading-meta">
                    <span className="badge badge-accent">
                        {TICKET_STATUS_LABEL[ticket.status]}
                    </span>
                    <span className="badge badge-off">
                        Prioritas{' '}
                        {TICKET_PRIORITY_LABEL[ticket.priority].toLowerCase()}
                    </span>
                </div>
            </header>

            {error && <Alert>{error}</Alert>}
            {success && <Alert kind="success">{success}</Alert>}

            <div className="ticket-detail-grid">
                <main className="ticket-detail-main">
                    <section className="panel">
                        <p className="ticket-description">
                            {ticket.description}
                        </p>
                        <AttachmentList
                            attachments={ticket.attachments}
                            onDownload={downloadAttachment}
                        />
                    </section>

                    {ticket.resolution && (
                        <section className="panel ticket-resolution">
                            <h3>Penyelesaian</h3>
                            <p>{ticket.resolution}</p>
                        </section>
                    )}

                    {(can.close || can.reopen) && (
                        <section className="panel ticket-actions">
                            {can.close && (
                                <>
                                    <span>Apakah masalah sudah teratasi?</span>
                                    <button
                                        className="btn btn-primary"
                                        type="button"
                                        disabled={saving}
                                        onClick={() =>
                                            void performAction(() =>
                                                ticketService.close(id),
                                            )
                                        }
                                    >
                                        Ya, tutup tiket
                                    </button>
                                </>
                            )}
                            {can.reopen && (
                                <button
                                    className="btn"
                                    type="button"
                                    disabled={saving}
                                    onClick={() =>
                                        void performAction(() =>
                                            ticketService.reopen(id),
                                        )
                                    }
                                >
                                    Buka kembali
                                </button>
                            )}
                        </section>
                    )}

                    <section className="ticket-timeline">
                        <h3>Riwayat tiket</h3>
                        {timeline.length === 0 ? (
                            <p className="muted">Belum ada aktivitas.</p>
                        ) : (
                            <ol>
                                {timeline.map((item) => (
                                    <li
                                        key={item.id}
                                        className={
                                            item.type === 'event'
                                                ? 'ticket-event'
                                                : 'panel'
                                        }
                                    >
                                        <div className="ticket-timeline-meta">
                                            <strong>
                                                {item.user ?? 'Sistem'}
                                                {item.type === 'internal' && (
                                                    <span className="badge badge-warning">
                                                        Catatan internal · IT
                                                    </span>
                                                )}
                                            </strong>
                                            <time>
                                                {formatTicketDate(
                                                    item.created_at,
                                                )}
                                            </time>
                                        </div>
                                        <p>{item.body}</p>
                                        <AttachmentList
                                            attachments={item.attachments}
                                            onDownload={downloadAttachment}
                                        />
                                    </li>
                                ))}
                            </ol>
                        )}
                    </section>

                    {can.comment ? (
                        <form
                            className="panel"
                            onSubmit={(event) => void sendComment(event)}
                        >
                            <h3>
                                {ticket.status === 'waiting' && !can.it
                                    ? 'Balas pertanyaan IT'
                                    : 'Tambah pesan'}
                            </h3>
                            <Field label="Pesan">
                                <textarea
                                    rows={4}
                                    value={body}
                                    onChange={(event) =>
                                        setBody(event.target.value)
                                    }
                                />
                            </Field>
                            <Field label="Lampiran (opsional)">
                                <input
                                    type="file"
                                    multiple
                                    accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.log,.doc,.docx,.xls,.xlsx"
                                    onChange={(event) =>
                                        setFiles(
                                            Array.from(
                                                event.target.files ?? [],
                                            ),
                                        )
                                    }
                                />
                            </Field>
                            {can.it && (
                                <label className="check">
                                    <input
                                        type="checkbox"
                                        checked={isInternal}
                                        onChange={(event) =>
                                            setIsInternal(event.target.checked)
                                        }
                                    />
                                    Catatan internal (hanya terlihat oleh IT)
                                </label>
                            )}
                            <button
                                className="btn btn-primary"
                                type="submit"
                                disabled={saving || !body.trim()}
                            >
                                Kirim pesan
                            </button>
                        </form>
                    ) : (
                        <p className="muted">
                            Tiket sudah ditutup. Buka kembali jika masalah
                            muncul lagi.
                        </p>
                    )}
                </main>

                <aside className="ticket-detail-aside">
                    <section className="panel">
                        <h3>Informasi tiket</h3>
                        <dl>
                            <dt>Pemohon</dt>
                            <dd>
                                {ticket.requester.name}{' '}
                                <span className="muted">
                                    {ticket.requester.email}
                                </span>
                            </dd>
                            <dt>Ditangani oleh</dt>
                            <dd>
                                {ticket.assignee?.name ?? 'Belum di-assign'}
                            </dd>
                            <dt>Kategori</dt>
                            <dd>
                                {categories[ticket.category] ?? ticket.category}
                            </dd>
                            <dt>Lokasi / aset</dt>
                            <dd>{ticket.location ?? '—'}</dd>
                            <dt>Kontak</dt>
                            <dd>{ticket.contact_phone ?? '—'}</dd>
                            <dt>Dibuat</dt>
                            <dd>{formatTicketDate(ticket.created_at)}</dd>
                            <dt>Respon pertama IT</dt>
                            <dd>
                                {formatTicketDate(ticket.first_response_at)}
                            </dd>
                            <dt>Teratasi</dt>
                            <dd>{formatTicketDate(ticket.resolved_at)}</dd>
                        </dl>
                    </section>

                    {can.it && edit && (
                        <section className="panel">
                            <h3>Penanganan IT</h3>
                            {ticket.status !== 'closed' && (
                                <button
                                    className="btn ticket-take-button"
                                    type="button"
                                    disabled={saving}
                                    onClick={() =>
                                        void performAction(() =>
                                            ticketService.take(id),
                                        )
                                    }
                                >
                                    Ambil tiket ini
                                </button>
                            )}
                            <form onSubmit={(event) => void saveTicket(event)}>
                                <Field label="Status">
                                    <select
                                        value={edit.status}
                                        onChange={(event) =>
                                            setEdit({
                                                ...edit,
                                                status: event.target
                                                    .value as TicketStatus,
                                            })
                                        }
                                    >
                                        {Object.entries(
                                            TICKET_STATUS_LABEL,
                                        ).map(([value, label]) => (
                                            <option key={value} value={value}>
                                                {label}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                <Field label="Prioritas">
                                    <select
                                        value={edit.priority}
                                        onChange={(event) =>
                                            setEdit({
                                                ...edit,
                                                priority: event.target
                                                    .value as TicketPriority,
                                            })
                                        }
                                    >
                                        {Object.entries(
                                            TICKET_PRIORITY_LABEL,
                                        ).map(([value, label]) => (
                                            <option key={value} value={value}>
                                                {label}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                <Field label="Kategori">
                                    <select
                                        value={edit.category}
                                        onChange={(event) =>
                                            setEdit({
                                                ...edit,
                                                category: event.target.value,
                                            })
                                        }
                                    >
                                        {Object.entries(categories).map(
                                            ([value, label]) => (
                                                <option
                                                    key={value}
                                                    value={value}
                                                >
                                                    {label}
                                                </option>
                                            ),
                                        )}
                                    </select>
                                </Field>
                                <Field label="Ditangani oleh">
                                    <select
                                        value={edit.assignee_id ?? ''}
                                        onChange={(event) =>
                                            setEdit({
                                                ...edit,
                                                assignee_id: event.target.value
                                                    ? Number(event.target.value)
                                                    : null,
                                            })
                                        }
                                    >
                                        <option value="">Belum ada</option>
                                        {staff.map((member) => (
                                            <option
                                                key={member.id}
                                                value={member.id}
                                            >
                                                {member.name}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                <Field
                                    label="Catatan penyelesaian"
                                    hint="Wajib diisi saat status Teratasi."
                                >
                                    <textarea
                                        rows={4}
                                        value={edit.resolution}
                                        onChange={(event) =>
                                            setEdit({
                                                ...edit,
                                                resolution: event.target.value,
                                            })
                                        }
                                    />
                                </Field>
                                <button
                                    className="btn btn-primary btn-block"
                                    type="submit"
                                    disabled={saving}
                                >
                                    Simpan perubahan
                                </button>
                            </form>
                        </section>
                    )}
                </aside>
            </div>
        </section>
    );
}
