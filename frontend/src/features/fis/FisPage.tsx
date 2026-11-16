import { useCallback, useEffect, useMemo, useState, type FormEvent } from 'react';
import { Alert, Loader } from '../../components/common/Feedback';
import { Field } from '../../components/common/Field';
import { Modal } from '../../components/common/Modal';
import { DataTable, type Column } from '../../components/tables/DataTable';
import { Pagination } from '../../components/tables/Pagination';
import { usePermission } from '../../hooks/usePermission';
import {
    fisService,
    type Flight,
    type FlightAccommodation,
    type FlightCrew,
    type FlightHandlingAgent,
    type FlightPayload,
    type FlightPassenger,
    type FlightSchedule,
} from '../../services/fis.service';
import { errorMessage } from '../../utils/error';
import { AirportPicker } from './AirportPicker';

type RowsEditorProps<T extends object> = {
    title: string;
    rows: T[];
    fields: Array<{
        key: keyof T;
        label: string;
        type?: string;
        airport?: boolean;
    }>;
    onChange: (index: number, field: string, value: string) => void;
    onAdd: () => void;
    onRemove: (index: number) => void;
};

function RowsEditor<T extends object>({
    title,
    rows,
    fields,
    onChange,
    onAdd,
    onRemove,
}: RowsEditorProps<T>) {
    return (
        <section className="panel fis-section">
            <div className="fis-section-head">
                <h3>{title}</h3>
                <button type="button" className="btn btn-ghost" onClick={onAdd}>
                    + Tambah
                </button>
            </div>
            {rows.map((row, index) => (
                <div className="fis-row" key={index}>
                    <div className="fis-fields">
                        {fields.map((field) => (
                            <Field key={String(field.key)} label={field.label}>
                                {field.airport ? (
                                    <AirportPicker
                                        value={String(row[field.key] ?? '')}
                                        onChange={(value) =>
                                            onChange(index, String(field.key), value)
                                        }
                                    />
                                ) : (
                                    <input
                                        className="input"
                                        type={field.type ?? 'text'}
                                        value={String(row[field.key] ?? '')}
                                        onChange={(event) =>
                                            onChange(
                                                index,
                                                String(field.key),
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </Field>
                        ))}
                    </div>
                    <button
                        type="button"
                        className="btn btn-danger"
                        onClick={() => onRemove(index)}
                    >
                        Hapus
                    </button>
                </div>
            ))}
            {rows.length === 0 && (
                <p className="muted">Belum ada data. Klik “Tambah” untuk mengisi.</p>
            )}
        </section>
    );
}

const otherFields = [
    ['flight_sec_clearance', 'Flight Security Clearance'],
    ['flight_approval', 'Flight Approval'],
    ['landing_permit', 'Landing Permit'],
    ['overflight_permit', 'Overflight Permit'],
    ['fuel_on_board', 'Fuel on Board'],
    ['notam', 'NOTAM'],
    ['parking_stand', 'Parking Stand'],
] as const;

function emptyFlight(): FlightPayload {
    return {
        flight_no: '',
        date: new Date().toISOString().slice(0, 10),
        chartered_by: '',
        aircraft_registration_id: 0,
        schedules: [
            {
                date: new Date().toISOString().slice(0, 10),
                from: '',
                to: '',
                etd: '',
                eta: '',
                remarks: '',
            },
        ],
        crews: [],
        handling_agents: [],
        passengers: [],
        accommodations: [],
        other: {},
    };
}

function formatDuration(minutes: number): string {
    return `${Math.floor(minutes / 60)}j ${String(minutes % 60).padStart(2, '0')}m`;
}

function formatUtc(value: string): string {
    return value.replace('T', ' ').slice(0, 16);
}

export default function FisPage() {
    const { canEdit, isMaster } = usePermission();
    const canManage = isMaster || canEdit('fis');
    const [flights, setFlights] = useState<Flight[]>([]);
    const [aircraft, setAircraft] = useState<
        Awaited<ReturnType<typeof fisService.aircraft>>
    >([]);
    const [page, setPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [total, setTotal] = useState(0);
    const [searchInput, setSearchInput] = useState('');
    const [search, setSearch] = useState('');
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [editing, setEditing] = useState<Flight | null>(null);
    const [details, setDetails] = useState<Flight | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [form, setForm] = useState<FlightPayload>(emptyFlight);
    const [formError, setFormError] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);
    const [reportingFlightId, setReportingFlightId] = useState<number | null>(
        null,
    );

    const loadFlights = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const result = await fisService.list({
                q: search || undefined,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
                page,
            });
            setFlights(result.data);
            setLastPage(result.last_page);
            setTotal(result.total);
        } catch (loadError) {
            setError(errorMessage(loadError));
        } finally {
            setLoading(false);
        }
    }, [dateFrom, dateTo, page, search]);

    useEffect(() => {
        void loadFlights();
    }, [loadFlights]);

    useEffect(() => {
        fisService
            .aircraft()
            .then(setAircraft)
            .catch((loadError: unknown) => setError(errorMessage(loadError)));
    }, []);

    function openCreate(): void {
        setEditing(null);
        setForm(emptyFlight());
        setFormError(null);
        setFormOpen(true);
    }

    function openEdit(flight: Flight): void {
        setEditing(flight);
        setForm({
            flight_no: flight.flight_no,
            date: flight.date,
            chartered_by: flight.chartered_by,
            aircraft_registration_id: flight.aircraft_registration.id,
            schedules: flight.schedules.map((schedule) => ({
                ...schedule,
                remarks: schedule.remarks ?? '',
            })),
            crews: flight.crews.map((crew) => ({ ...crew })),
            handling_agents: flight.handling_agents.map((agent) => ({ ...agent })),
            passengers: flight.passengers.map((passenger) => ({ ...passenger })),
            accommodations: flight.accommodations.map((stay) => ({ ...stay })),
            other: { ...flight.other },
        });
        setFormError(null);
        setFormOpen(true);
    }

    function updateRow(
        section: 'schedules' | 'crews' | 'handling_agents' | 'passengers' | 'accommodations',
        index: number,
        field: string,
        value: string,
    ): void {
        setForm((current) => {
            if (section === 'schedules') {
                return {
                    ...current,
                    schedules: current.schedules.map((row, rowIndex) =>
                        rowIndex === index ? { ...row, [field]: value } : row,
                    ),
                };
            }
            if (section === 'crews') {
                return {
                    ...current,
                    crews: current.crews.map((row, rowIndex) =>
                        rowIndex === index ? { ...row, [field]: value } : row,
                    ),
                };
            }
            if (section === 'handling_agents') {
                return {
                    ...current,
                    handling_agents: current.handling_agents.map((row, rowIndex) =>
                        rowIndex === index ? { ...row, [field]: value } : row,
                    ),
                };
            }
            if (section === 'passengers') {
                return {
                    ...current,
                    passengers: current.passengers.map((row, rowIndex) =>
                        rowIndex === index ? { ...row, [field]: value } : row,
                    ),
                };
            }
            return {
                ...current,
                accommodations: current.accommodations.map((row, rowIndex) =>
                    rowIndex === index ? { ...row, [field]: value } : row,
                ),
            };
        });
    }

    function removeRow(
        section: 'schedules' | 'crews' | 'handling_agents' | 'passengers' | 'accommodations',
        index: number,
    ): void {
        setForm((current) => ({
            ...current,
            [section]: current[section].filter((_, rowIndex) => rowIndex !== index),
        }));
    }

    async function submit(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        setSaving(true);
        setFormError(null);

        const payload: FlightPayload = {
            ...form,
            flight_no: form.flight_no.trim(),
            chartered_by: form.chartered_by.trim(),
            schedules: form.schedules.map((row) => ({
                ...row,
                from: row.from.trim().toUpperCase(),
                to: row.to.trim().toUpperCase(),
                remarks: row.remarks?.trim() || null,
            })),
            crews: form.crews.map((row) => ({
                ...row,
                name: row.name.trim(),
                position: row.position.trim(),
                phone: row.phone.trim(),
                remarks: row.remarks.trim(),
            })),
            handling_agents: form.handling_agents.map((row) => ({
                ...row,
                location: row.location.trim().toUpperCase(),
                company: row.company.trim(),
                contact_person: row.contact_person.trim(),
                phone: row.phone.trim(),
            })),
            passengers: form.passengers.map((row) => ({
                ...row,
                name: row.name.trim(),
                remarks: row.remarks.trim(),
            })),
            accommodations: form.accommodations.map((row) => ({
                ...row,
                location: row.location.trim().toUpperCase(),
                hotel: row.hotel.trim(),
                address: row.address.trim(),
                phone: row.phone.trim(),
            })),
            other: Object.fromEntries(
                Object.entries(form.other).map(([key, value]) => [
                    key,
                    value.trim(),
                ]),
            ),
        };

        try {
            if (editing) await fisService.update(editing.id, payload);
            else await fisService.create(payload);
            setFormOpen(false);
            await loadFlights();
        } catch (saveError) {
            setFormError(errorMessage(saveError));
        } finally {
            setSaving(false);
        }
    }

    async function removeFlight(flight: Flight): Promise<void> {
        if (!window.confirm(`Hapus flight ${flight.flight_no}?`)) return;
        try {
            await fisService.remove(flight.id);
            await loadFlights();
        } catch (deleteError) {
            setError(errorMessage(deleteError));
        }
    }

    async function downloadReport(flight: Flight): Promise<void> {
        setReportingFlightId(flight.id);
        setError(null);
        try {
            const blob = await fisService.report(flight.id);
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `flight-information-${flight.flight_no}-${flight.date}.pdf`;
            link.click();
            window.setTimeout(() => URL.revokeObjectURL(url), 1000);
        } catch (reportError) {
            setError(errorMessage(reportError));
        } finally {
            setReportingFlightId(null);
        }
    }

    const columns = useMemo<Column<Flight>[]>(
        () => [
            {
                header: 'Flight',
                render: (flight) => (
                    <div>
                        <strong>{flight.flight_no}</strong>
                        <div className="muted">{flight.chartered_by}</div>
                    </div>
                ),
            },
            {
                header: 'Tanggal',
                render: (flight) =>
                    new Date(`${flight.date}T00:00:00`).toLocaleDateString('id-ID', {
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric',
                    }),
            },
            {
                header: 'Pesawat',
                render: (flight) => (
                    <div>
                        <strong>{flight.aircraft_registration.registration}</strong>
                        <div className="muted">
                            {flight.aircraft_registration.aircraft_type}
                        </div>
                    </div>
                ),
            },
            {
                header: 'Rute',
                render: (flight) =>
                    flight.schedules.map((leg) => `${leg.from}–${leg.to}`).join(' · '),
            },
            {
                header: 'Durasi',
                render: (flight) => formatDuration(flight.total_duration_minutes),
            },
            {
                header: 'Aksi',
                render: (flight) => (
                    <div className="row-actions">
                        <button
                            type="button"
                            className="btn btn-ghost"
                            onClick={() => setDetails(flight)}
                        >
                            Detail
                        </button>
                        <button
                            type="button"
                            className="btn btn-ghost"
                            disabled={reportingFlightId === flight.id}
                            onClick={() => void downloadReport(flight)}
                        >
                            {reportingFlightId === flight.id
                                ? 'Menyiapkan...'
                                : 'Report PDF'}
                        </button>
                        {canManage && (
                            <>
                                <button
                                    type="button"
                                    className="btn btn-ghost"
                                    onClick={() => openEdit(flight)}
                                >
                                    Edit
                                </button>
                                <button
                                    type="button"
                                    className="btn btn-danger"
                                    onClick={() => void removeFlight(flight)}
                                >
                                    Hapus
                                </button>
                            </>
                        )}
                    </div>
                ),
            },
        ],
        [canManage],
    );

    return (
        <div className="fis-page">
            <div className="page-head page-head-row">
                <div>
                    <h2>Flight Information System</h2>
                    <p className="muted">
                        Kelola jadwal penerbangan, kru, penumpang, dan kebutuhan operasional.
                    </p>
                </div>
                {canManage && (
                    <button
                        type="button"
                        className="btn btn-primary"
                        onClick={openCreate}
                    >
                        + Input Flight
                    </button>
                )}
            </div>

            {error && <Alert>{error}</Alert>}

            <div className="toolbar fis-toolbar">
                <input
                    className="input"
                    placeholder="Cari flight, charter, atau registrasi..."
                    value={searchInput}
                    onChange={(event) => setSearchInput(event.target.value)}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            setPage(1);
                            setSearch(searchInput.trim());
                        }
                    }}
                />
                <Field label="Dari tanggal">
                    <input
                        className="input"
                        type="date"
                        value={dateFrom}
                        onChange={(event) => {
                            setPage(1);
                            setDateFrom(event.target.value);
                        }}
                    />
                </Field>
                <Field label="Sampai tanggal">
                    <input
                        className="input"
                        type="date"
                        value={dateTo}
                        onChange={(event) => {
                            setPage(1);
                            setDateTo(event.target.value);
                        }}
                    />
                </Field>
                <button
                    type="button"
                    className="btn btn-ghost"
                    onClick={() => {
                        setPage(1);
                        setSearch(searchInput.trim());
                    }}
                >
                    Cari
                </button>
            </div>

            {loading ? (
                <Loader />
            ) : (
                <>
                    <DataTable
                        columns={columns}
                        rows={flights}
                        rowKey={(flight) => flight.id}
                        empty="Belum ada data penerbangan."
                    />
                    <Pagination
                        page={page}
                        lastPage={lastPage}
                        total={total}
                        onChange={setPage}
                    />
                </>
            )}

            {formOpen && (
                <Modal
                    title={editing ? 'Edit Flight' : 'Input Flight'}
                    onClose={() => setFormOpen(false)}
                >
                    <form className="fis-form" onSubmit={(event) => void submit(event)}>
                        {formError && <Alert>{formError}</Alert>}
                        <div className="fis-fields">
                            <Field label="Flight No.">
                                <input
                                    className="input"
                                    required
                                    maxLength={20}
                                    value={form.flight_no}
                                    onChange={(event) =>
                                        setForm({ ...form, flight_no: event.target.value })
                                    }
                                />
                            </Field>
                            <Field label="Tanggal Flight">
                                <input
                                    className="input"
                                    type="date"
                                    required
                                    value={form.date}
                                    onChange={(event) =>
                                        setForm({ ...form, date: event.target.value })
                                    }
                                />
                            </Field>
                            <Field label="Chartered By">
                                <input
                                    className="input"
                                    required
                                    maxLength={150}
                                    value={form.chartered_by}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            chartered_by: event.target.value,
                                        })
                                    }
                                />
                            </Field>
                            <Field label="Registrasi Pesawat">
                                <select
                                    className="input"
                                    required
                                    value={form.aircraft_registration_id || ''}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            aircraft_registration_id: Number(
                                                event.target.value,
                                            ),
                                        })
                                    }
                                >
                                    <option value="">Pilih pesawat</option>
                                    {aircraft.map((item) => (
                                        <option key={item.id} value={item.id}>
                                            {item.registration} — {item.aircraft_type} (
                                            {item.seat_capacity} kursi)
                                        </option>
                                    ))}
                                </select>
                            </Field>
                        </div>

                        <RowsEditor<FlightSchedule>
                            title="Jadwal Penerbangan (ETD/ETA UTC)"
                            rows={form.schedules}
                            fields={[
                                { key: 'date', label: 'Tanggal', type: 'date' },
                                { key: 'from', label: 'Dari (IATA / ICAO)', airport: true },
                                { key: 'to', label: 'Ke (IATA / ICAO)', airport: true },
                                { key: 'etd', label: 'ETD (UTC)', type: 'datetime-local' },
                                { key: 'eta', label: 'ETA (UTC)', type: 'datetime-local' },
                                { key: 'remarks', label: 'Catatan' },
                            ]}
                            onChange={(index, field, value) =>
                                updateRow('schedules', index, field, value)
                            }
                            onAdd={() =>
                                setForm((current) => ({
                                    ...current,
                                    schedules: [
                                        ...current.schedules,
                                        {
                                            date: current.date,
                                            from: '',
                                            to: '',
                                            etd: '',
                                            eta: '',
                                            remarks: '',
                                        },
                                    ],
                                }))
                            }
                            onRemove={(index) => removeRow('schedules', index)}
                        />
                        <RowsEditor<FlightCrew>
                            title="Crew"
                            rows={form.crews}
                            fields={[
                                { key: 'name', label: 'Nama' },
                                { key: 'position', label: 'Posisi' },
                                { key: 'phone', label: 'Telepon' },
                                { key: 'remarks', label: 'Catatan' },
                            ]}
                            onChange={(index, field, value) =>
                                updateRow('crews', index, field, value)
                            }
                            onAdd={() =>
                                setForm((current) => ({
                                    ...current,
                                    crews: [
                                        ...current.crews,
                                        { name: '', position: '', phone: '', remarks: '' },
                                    ],
                                }))
                            }
                            onRemove={(index) => removeRow('crews', index)}
                        />
                        <RowsEditor<FlightHandlingAgent>
                            title="Handling Agent"
                            rows={form.handling_agents}
                            fields={[
                                { key: 'location', label: 'Lokasi (IATA / ICAO)', airport: true },
                                { key: 'company', label: 'Perusahaan' },
                                { key: 'contact_person', label: 'PIC' },
                                { key: 'phone', label: 'Telepon' },
                            ]}
                            onChange={(index, field, value) =>
                                updateRow('handling_agents', index, field, value)
                            }
                            onAdd={() =>
                                setForm((current) => ({
                                    ...current,
                                    handling_agents: [
                                        ...current.handling_agents,
                                        {
                                            location: '',
                                            company: '',
                                            contact_person: '',
                                            phone: '',
                                        },
                                    ],
                                }))
                            }
                            onRemove={(index) => removeRow('handling_agents', index)}
                        />
                        <RowsEditor<FlightPassenger>
                            title="Penumpang"
                            rows={form.passengers}
                            fields={[
                                { key: 'name', label: 'Nama' },
                                { key: 'remarks', label: 'Catatan' },
                            ]}
                            onChange={(index, field, value) =>
                                updateRow('passengers', index, field, value)
                            }
                            onAdd={() =>
                                setForm((current) => ({
                                    ...current,
                                    passengers: [
                                        ...current.passengers,
                                        { name: '', remarks: '' },
                                    ],
                                }))
                            }
                            onRemove={(index) => removeRow('passengers', index)}
                        />
                        <RowsEditor<FlightAccommodation>
                            title="Akomodasi Crew"
                            rows={form.accommodations}
                            fields={[
                                { key: 'location', label: 'Lokasi (IATA / ICAO)', airport: true },
                                { key: 'hotel', label: 'Hotel' },
                                { key: 'address', label: 'Alamat' },
                                { key: 'phone', label: 'Telepon' },
                            ]}
                            onChange={(index, field, value) =>
                                updateRow('accommodations', index, field, value)
                            }
                            onAdd={() =>
                                setForm((current) => ({
                                    ...current,
                                    accommodations: [
                                        ...current.accommodations,
                                        { location: '', hotel: '', address: '', phone: '' },
                                    ],
                                }))
                            }
                            onRemove={(index) => removeRow('accommodations', index)}
                        />
                        <section className="panel fis-section">
                            <h3>Informasi Lain</h3>
                            <div className="fis-fields">
                                {otherFields.map(([key, label]) => (
                                    <Field key={key} label={label}>
                                        <input
                                            className="input"
                                            value={form.other[key] ?? ''}
                                            onChange={(event) =>
                                                setForm((current) => ({
                                                    ...current,
                                                    other: {
                                                        ...current.other,
                                                        [key]: event.target.value,
                                                    },
                                                }))
                                            }
                                        />
                                    </Field>
                                ))}
                            </div>
                        </section>

                        <div className="row-actions fis-form-actions">
                            <button
                                type="button"
                                className="btn btn-ghost"
                                onClick={() => setFormOpen(false)}
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                className="btn btn-primary"
                                disabled={saving}
                            >
                                {saving ? 'Menyimpan...' : 'Simpan Flight'}
                            </button>
                        </div>
                    </form>
                </Modal>
            )}

            {details && (
                <Modal
                    title={`Flight ${details.flight_no}`}
                    onClose={() => setDetails(null)}
                >
                    <div className="fis-detail">
                        <p>
                            <strong>Tanggal:</strong> {details.date} ·{' '}
                            <strong>Chartered By:</strong> {details.chartered_by}
                        </p>
                        <p>
                            <strong>Pesawat:</strong>{' '}
                            {details.aircraft_registration.registration} —{' '}
                            {details.aircraft_registration.aircraft_type} ·{' '}
                            {details.aircraft_registration.seat_capacity} kursi
                        </p>
                        <h3>Jadwal</h3>
                        <DataTable
                            columns={[
                                { header: 'Tanggal', render: (leg) => leg.date },
                                { header: 'Rute', render: (leg) => `${leg.from}–${leg.to}` },
                                {
                                    header: 'ETD / ETA (UTC)',
                                    render: (leg) =>
                                        `${formatUtc(leg.etd)} / ${formatUtc(leg.eta)}`,
                                },
                                {
                                    header: 'Durasi',
                                    render: (leg) =>
                                        formatDuration(leg.duration_minutes ?? 0),
                                },
                                { header: 'Catatan', render: (leg) => leg.remarks ?? '—' },
                            ]}
                            rows={details.schedules}
                            rowKey={(leg) =>
                                `${details.id}-${leg.date}-${leg.etd}-${leg.from}-${leg.to}`
                            }
                        />
                        <h3>Crew</h3>
                        <DataTable
                            columns={[
                                { header: 'Nama', render: (crew) => crew.name },
                                { header: 'Posisi', render: (crew) => crew.position || '—' },
                                { header: 'Telepon', render: (crew) => crew.phone || '—' },
                                { header: 'Catatan', render: (crew) => crew.remarks || '—' },
                            ]}
                            rows={details.crews}
                            rowKey={(crew) => `${details.id}-${crew.name}-${crew.position}`}
                        />
                        <h3>
                            Penumpang ({details.passengers.length}/
                            {details.aircraft_registration.seat_capacity} kursi)
                        </h3>
                        <DataTable
                            columns={[
                                { header: 'Nama', render: (passenger) => passenger.name },
                                {
                                    header: 'Catatan',
                                    render: (passenger) => passenger.remarks || '—',
                                },
                            ]}
                            rows={details.passengers}
                            rowKey={(passenger) => `${details.id}-${passenger.name}`}
                        />
                        <h3>Handling Agent</h3>
                        <DataTable
                            columns={[
                                { header: 'Lokasi', render: (agent) => agent.location },
                                { header: 'Perusahaan', render: (agent) => agent.company || '—' },
                                {
                                    header: 'PIC',
                                    render: (agent) => agent.contact_person || '—',
                                },
                                { header: 'Telepon', render: (agent) => agent.phone || '—' },
                            ]}
                            rows={details.handling_agents}
                            rowKey={(agent) =>
                                `${details.id}-${agent.location}-${agent.company}`
                            }
                        />
                        <h3>Akomodasi Crew</h3>
                        <DataTable
                            columns={[
                                { header: 'Lokasi', render: (stay) => stay.location },
                                { header: 'Hotel', render: (stay) => stay.hotel },
                                { header: 'Alamat', render: (stay) => stay.address || '—' },
                                { header: 'Telepon', render: (stay) => stay.phone || '—' },
                            ]}
                            rows={details.accommodations}
                            rowKey={(stay) =>
                                `${details.id}-${stay.location}-${stay.hotel}`
                            }
                        />
                        {details.other && Object.values(details.other).some(Boolean) && (
                            <>
                                <h3>Informasi Lain</h3>
                                <dl className="fis-other-list">
                                    {otherFields.map(([key, label]) =>
                                        details.other[key] ? (
                                            <div key={key}>
                                                <dt>{label}</dt>
                                                <dd>{details.other[key]}</dd>
                                            </div>
                                        ) : null,
                                    )}
                                </dl>
                            </>
                        )}
                    </div>
                </Modal>
            )}
        </div>
    );
}
