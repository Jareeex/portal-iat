import {
    useCallback,
    useEffect,
    useMemo,
    useState,
    type FormEvent,
} from 'react';
import { Link } from 'react-router-dom';
import { Alert, Loader } from '../../components/common/Feedback';
import { DataTable, type Column } from '../../components/tables/DataTable';
import { Pagination } from '../../components/tables/Pagination';
import {
    ticketService,
    type TicketListParams,
} from '../../services/ticket.service';
import { errorMessage } from '../../utils/error';
import {
    formatTicketDate,
    TICKET_PRIORITY_LABEL,
    TICKET_STATUS_LABEL,
    type TicketIndexResponse,
    type TicketStatus,
    type TicketSummary,
} from '../../types/ticket';

const statuses: Array<{ value: string; label: string }> = [
    { value: 'active', label: 'Aktif' },
    { value: 'open', label: 'Baru' },
    { value: 'in_progress', label: 'Diproses' },
    { value: 'waiting', label: 'Menunggu user' },
    { value: 'resolved', label: 'Teratasi' },
    { value: 'closed', label: 'Ditutup' },
    { value: 'all', label: 'Semua' },
];

function StatusBadge({ status }: { status: TicketStatus }) {
    const tone =
        status === 'resolved'
            ? 'badge-ok'
            : status === 'closed'
              ? 'badge-off'
              : 'badge-accent';

    return (
        <span className={`badge ${tone}`}>{TICKET_STATUS_LABEL[status]}</span>
    );
}

function Stat({ label, value }: { label: string; value: number }) {
    return (
        <div className="panel">
            <span className="muted">{label}</span>
            <strong className="ticket-stat-value">{value}</strong>
        </div>
    );
}

export default function ItTicketsPage() {
    const [result, setResult] = useState<TicketIndexResponse | null>(null);
    const [page, setPage] = useState(1);
    const [status, setStatus] = useState('active');
    const [priority, setPriority] = useState('');
    const [category, setCategory] = useState('');
    const [assignee, setAssignee] = useState('');
    const [queryInput, setQueryInput] = useState('');
    const [query, setQuery] = useState('');
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const loadTickets = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const params: TicketListParams = {
                page,
                status,
                priority: priority || undefined,
                category: category || undefined,
                assignee: assignee || undefined,
                q: query || undefined,
            };
            setResult(await ticketService.list(params));
        } catch (loadError) {
            setError(errorMessage(loadError));
        } finally {
            setLoading(false);
        }
    }, [assignee, category, page, priority, query, status]);

    useEffect(() => {
        void loadTickets();
    }, [loadTickets]);

    const columns = useMemo<Column<TicketSummary>[]>(() => {
        const items: Column<TicketSummary>[] = [
            {
                header: 'Tiket',
                render: (ticket) => (
                    <div>
                        <Link
                            className="ticket-title"
                            to={`/it/tickets/${ticket.id}`}
                        >
                            {ticket.title}
                        </Link>
                        <div className="muted">
                            {ticket.number} ·{' '}
                            {result?.categories[ticket.category] ??
                                ticket.category}
                        </div>
                    </div>
                ),
            },
            {
                header: 'Prioritas',
                render: (ticket) => (
                    <span
                        className={`badge ${ticket.priority === 'critical' ? 'badge-danger' : 'badge-off'}`}
                    >
                        {TICKET_PRIORITY_LABEL[ticket.priority]}
                    </span>
                ),
            },
            {
                header: 'Status',
                render: (ticket) => <StatusBadge status={ticket.status} />,
            },
        ];

        if (result?.can.it) {
            items.push({
                header: 'Pemohon',
                render: (ticket) => ticket.requester ?? '—',
            });
        }

        items.push(
            {
                header: 'Ditangani',
                render: (ticket) => ticket.assignee ?? 'Belum di-assign',
            },
            {
                header: 'Update',
                render: (ticket) => formatTicketDate(ticket.updated_at),
            },
        );

        return items;
    }, [result?.can.it, result?.categories]);

    function submitSearch(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setPage(1);
        setQuery(queryInput.trim());
    }

    function updateFilter(setter: (value: string) => void, value: string) {
        setPage(1);
        setter(value);
    }

    return (
        <section>
            <header className="page-head page-head-row">
                <div>
                    <h2>{result?.can.it ? 'IT Tickets' : 'Tiket saya'}</h2>
                    <p className="muted">
                        {result?.can.it
                            ? 'Antrean laporan kendala dari seluruh user untuk departemen IT.'
                            : 'Laporkan kendala dan pantau tindak lanjut dari tim IT.'}
                    </p>
                </div>
                <Link className="btn btn-primary" to="/it/tickets/new">
                    Buat tiket
                </Link>
            </header>

            {error && <Alert>{error}</Alert>}

            {result && (
                <div className="card-grid ticket-stats">
                    {result.can.it ? (
                        <>
                            <Stat
                                label="Belum ditangani"
                                value={result.stats.unassigned}
                            />
                            <Stat
                                label="Sedang diproses"
                                value={result.stats.in_progress}
                            />
                            <Stat
                                label="Menunggu user"
                                value={result.stats.waiting}
                            />
                            <Stat
                                label="Kritis (aktif)"
                                value={result.stats.critical}
                            />
                        </>
                    ) : (
                        <>
                            <Stat
                                label="Tiket aktif"
                                value={
                                    result.stats.open +
                                    result.stats.in_progress +
                                    result.stats.waiting
                                }
                            />
                            <Stat
                                label="Teratasi"
                                value={result.stats.resolved}
                            />
                            <Stat label="Ditutup" value={result.stats.closed} />
                        </>
                    )}
                </div>
            )}

            <div
                className="ticket-status-tabs"
                aria-label="Filter status tiket"
            >
                {statuses.map((item) => (
                    <button
                        key={item.value}
                        type="button"
                        className={
                            status === item.value
                                ? 'btn btn-primary'
                                : 'btn btn-ghost'
                        }
                        onClick={() => updateFilter(setStatus, item.value)}
                    >
                        {item.label}
                    </button>
                ))}
            </div>

            <form className="toolbar" onSubmit={submitSearch}>
                <input
                    type="search"
                    aria-label="Cari tiket"
                    placeholder={
                        result?.can.it
                            ? 'Cari nomor, judul, atau pemohon'
                            : 'Cari nomor atau judul'
                    }
                    value={queryInput}
                    onChange={(event) => setQueryInput(event.target.value)}
                />
                <select
                    aria-label="Prioritas"
                    value={priority}
                    onChange={(event) =>
                        updateFilter(setPriority, event.target.value)
                    }
                >
                    <option value="">Semua prioritas</option>
                    {Object.entries(TICKET_PRIORITY_LABEL).map(
                        ([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ),
                    )}
                </select>
                <select
                    aria-label="Kategori"
                    value={category}
                    onChange={(event) =>
                        updateFilter(setCategory, event.target.value)
                    }
                >
                    <option value="">Semua kategori</option>
                    {Object.entries(result?.categories ?? {}).map(
                        ([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ),
                    )}
                </select>
                {result?.can.it && (
                    <select
                        aria-label="Penanggung jawab"
                        value={assignee}
                        onChange={(event) =>
                            updateFilter(setAssignee, event.target.value)
                        }
                    >
                        <option value="">Semua penanggung jawab</option>
                        <option value="me">Tiket saya</option>
                        <option value="none">Belum di-assign</option>
                        {result.staff.map((member) => (
                            <option key={member.id} value={member.id}>
                                {member.name}
                            </option>
                        ))}
                    </select>
                )}
                <button className="btn" type="submit">
                    Cari
                </button>
            </form>

            {loading ? (
                <Loader text="Memuat tiket..." />
            ) : result ? (
                <>
                    <DataTable
                        columns={columns}
                        rows={result.data}
                        rowKey={(ticket) => ticket.id}
                        empty="Belum ada tiket yang cocok."
                    />
                    <Pagination
                        page={result.meta.current_page}
                        lastPage={result.meta.last_page}
                        total={result.meta.total}
                        onChange={setPage}
                    />
                </>
            ) : null}
        </section>
    );
}
