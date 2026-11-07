import {
    useCallback,
    useEffect,
    useRef,
    useState,
    type ChangeEvent,
} from 'react';
import { Link } from 'react-router-dom';
import { Alert, Loader } from '../../components/common/Feedback';
import { trainingService } from '../../services/training.service';
import { errorMessage } from '../../utils/error';
import type {
    LicenseDocument,
    Status,
    TrainingDetailResponse,
    TrainingRecord,
} from './training';

interface TrainingCrewDetailPageProps {
    crewId: number;
    onDeleted: () => void;
}

const statusNames: Record<Status, string> = {
    expired: 'Expired',
    soon: 'Segera',
    valid: 'Berlaku',
};

function formatDate(value: string | null): string {
    if (!value) return '–';

    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) return '–';

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(date);
}

function RecordRow({
    crewId,
    record,
    manage,
    onSaved,
}: {
    crewId: number;
    record: TrainingRecord;
    manage: boolean;
    onSaved: () => void;
}) {
    const [editing, setEditing] = useState(false);
    const [busy, setBusy] = useState(false);
    const [dates, setDates] = useState({
        last_check: record.last_check ?? '',
        next_check: record.next_check ?? '',
        plan_check: record.plan_check ?? '',
    });
    const [error, setError] = useState<string | null>(null);

    async function save(): Promise<void> {
        setBusy(true);
        setError(null);
        try {
            await trainingService.saveRecord(crewId, record.type_id, dates);
            setEditing(false);
            onSaved();
        } catch (cause) {
            setError(errorMessage(cause));
        } finally {
            setBusy(false);
        }
    }

    return (
        <>
            <tr>
                <td>
                    {record.name}
                    {record.validity && (
                        <div className="muted">{record.validity}</div>
                    )}
                </td>
                {editing ? (
                    <>
                        {(
                            ['last_check', 'next_check', 'plan_check'] as const
                        ).map((field) => (
                            <td key={field}>
                                <input
                                    type="date"
                                    value={dates[field]}
                                    onChange={(event) =>
                                        setDates({
                                            ...dates,
                                            [field]: event.target.value,
                                        })
                                    }
                                />
                            </td>
                        ))}
                        <td />
                        <td className="training-row-actions">
                            <button
                                type="button"
                                className="btn btn-primary"
                                disabled={busy}
                                onClick={() => void save()}
                            >
                                Simpan
                            </button>
                            <button
                                type="button"
                                className="btn btn-ghost"
                                onClick={() => setEditing(false)}
                            >
                                Batal
                            </button>
                        </td>
                    </>
                ) : (
                    <>
                        <td>{formatDate(record.last_check)}</td>
                        <td>{formatDate(record.next_check)}</td>
                        <td>{formatDate(record.plan_check)}</td>
                        <td>
                            {record.status ? (
                                <span
                                    className={`badge badge-${record.status}`}
                                >
                                    {statusNames[record.status]}
                                    {record.days_left !== null
                                        ? ` · ${record.days_left} hari`
                                        : ''}
                                </span>
                            ) : (
                                '–'
                            )}
                        </td>
                        <td className="training-row-actions">
                            {manage && (
                                <button
                                    type="button"
                                    className="btn btn-ghost"
                                    onClick={() => setEditing(true)}
                                >
                                    Edit
                                </button>
                            )}
                        </td>
                    </>
                )}
            </tr>
            {error && (
                <tr>
                    <td colSpan={6}>
                        <Alert>{error}</Alert>
                    </td>
                </tr>
            )}
        </>
    );
}

function LicenseDocumentPreview({
    crewId,
    document,
}: {
    crewId: number;
    document: LicenseDocument;
}) {
    const [url, setUrl] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let active = true;
        let objectUrl: string | null = null;
        setUrl(null);
        setError(null);
        trainingService
            .showLicenseDocument(crewId, document.id)
            .then((file) => {
                objectUrl = URL.createObjectURL(file);
                if (active) setUrl(objectUrl);
            })
            .catch((cause: unknown) => {
                if (active) setError(errorMessage(cause));
            });

        return () => {
            active = false;
            if (objectUrl) URL.revokeObjectURL(objectUrl);
        };
    }, [crewId, document.id]);

    if (error) return <Alert>{error}</Alert>;
    if (!url) return <p className="muted">Memuat pratinjau PDF…</p>;

    return (
        <iframe
            className="training-license-preview"
            src={url}
            title={`Pratinjau ${document.name}`}
        />
    );
}

function LicenseDocuments({
    crewId,
    documents,
    manage,
    onUpdated,
}: {
    crewId: number;
    documents: LicenseDocument[];
    manage: boolean;
    onUpdated: () => void;
}) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [previewId, setPreviewId] = useState<number | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const previewDocument =
        documents.find((document) => document.id === previewId) ?? null;

    async function upload(event: ChangeEvent<HTMLInputElement>): Promise<void> {
        const files = Array.from(event.target.files ?? []);
        if (files.length === 0) return;
        setBusy(true);
        setError(null);
        try {
            await trainingService.uploadLicenseDocuments(crewId, files);
            onUpdated();
        } catch (cause) {
            setError(errorMessage(cause));
        } finally {
            setBusy(false);
            event.target.value = '';
        }
    }

    async function remove(document: LicenseDocument): Promise<void> {
        if (!window.confirm(`Hapus dokumen ${document.name}?`)) return;
        setBusy(true);
        setError(null);
        try {
            await trainingService.removeLicenseDocument(crewId, document.id);
            if (previewId === document.id) setPreviewId(null);
            onUpdated();
        } catch (cause) {
            setError(errorMessage(cause));
        } finally {
            setBusy(false);
        }
    }

    return (
        <section className="panel training-license-documents">
            <header className="page-head page-head-row">
                <div>
                    <h3>Dokumen lisensi</h3>
                    <p className="muted">
                        PDF privat untuk dokumen lisensi crew.
                    </p>
                </div>
                {manage && (
                    <>
                        <input
                            ref={inputRef}
                            className="training-file-input"
                            type="file"
                            accept="application/pdf,.pdf"
                            multiple
                            onChange={(event) => void upload(event)}
                        />
                        <button
                            type="button"
                            className="btn btn-primary"
                            disabled={busy}
                            onClick={() => inputRef.current?.click()}
                        >
                            {busy ? 'Memproses…' : 'Tambah PDF'}
                        </button>
                    </>
                )}
            </header>
            {error && <Alert>{error}</Alert>}
            {documents.length === 0 ? (
                <p className="muted">Belum ada dokumen lisensi.</p>
            ) : (
                <ul className="training-license-list">
                    {documents.map((document) => (
                        <li key={document.id}>
                            <span>{document.name}</span>
                            <div className="training-actions">
                                <button
                                    type="button"
                                    className="btn btn-ghost"
                                    onClick={() =>
                                        setPreviewId(
                                            previewId === document.id
                                                ? null
                                                : document.id,
                                        )
                                    }
                                >
                                    {previewId === document.id
                                        ? 'Tutup preview'
                                        : 'Preview'}
                                </button>
                                {manage && (
                                    <button
                                        type="button"
                                        className="btn btn-danger"
                                        disabled={busy}
                                        onClick={() => void remove(document)}
                                    >
                                        Hapus
                                    </button>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
            {previewDocument && (
                <LicenseDocumentPreview
                    crewId={crewId}
                    document={previewDocument}
                />
            )}
        </section>
    );
}

export default function TrainingCrewDetailPage({
    crewId,
    onDeleted,
}: TrainingCrewDetailPageProps) {
    const [data, setData] = useState<TrainingDetailResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [selectedAircraftId, setSelectedAircraftId] = useState('');
    const [aircraftBusy, setAircraftBusy] = useState(false);

    const load = useCallback(async () => {
        setError(null);
        try {
            setData(await trainingService.show(crewId));
        } catch (cause) {
            setError(errorMessage(cause));
        } finally {
            setLoading(false);
        }
    }, [crewId]);

    useEffect(() => {
        void load();
    }, [load]);

    useEffect(() => {
        if (data) {
            const availableAircrafts = data.aircraft_options.filter(
                (aircraft) =>
                    !data.crew.aircrafts.some(
                        (assigned) => assigned.id === aircraft.id,
                    ),
            );
            if (
                !availableAircrafts.some(
                    (aircraft) => String(aircraft.id) === selectedAircraftId,
                )
            ) {
                setSelectedAircraftId(
                    availableAircrafts[0]?.id.toString() ?? '',
                );
            }
        }
    }, [data, selectedAircraftId]);

    async function addAircraftToCrew(): Promise<void> {
        if (!data || !selectedAircraftId) return;
        setAircraftBusy(true);
        setError(null);
        try {
            await trainingService.addCrewAircraft(
                crewId,
                Number(selectedAircraftId),
            );
            await load();
        } catch (cause) {
            setError(errorMessage(cause));
        } finally {
            setAircraftBusy(false);
        }
    }

    async function removeAircraftFromCrew(
        aircraftId: number,
        aircraftLabel: string,
    ): Promise<void> {
        if (
            !window.confirm(
                `Hapus ${aircraftLabel} dari daftar pesawat crew ini?`,
            )
        )
            return;
        setAircraftBusy(true);
        setError(null);
        try {
            await trainingService.removeCrewAircraft(crewId, aircraftId);
            await load();
        } catch (cause) {
            setError(errorMessage(cause));
        } finally {
            setAircraftBusy(false);
        }
    }

    async function remove(): Promise<void> {
        if (
            !data ||
            !window.confirm(
                `Hapus ${data.crew.name} beserta semua record training?`,
            )
        )
            return;
        try {
            await trainingService.remove(crewId);
            onDeleted();
        } catch (cause) {
            setError(errorMessage(cause));
        }
    }

    if (loading) return <Loader />;
    if (!data)
        return (
            <section className="training-page">
                {error && <Alert>{error}</Alert>}
            </section>
        );

    return (
        <section className="training-page">
            <Link to="/training" className="training-back-link">
                ← Semua crew
            </Link>
            <header className="page-head page-head-row">
                <div>
                    <h2>
                        {data.crew.name}
                        {data.crew.code ? ` (${data.crew.code})` : ''}
                    </h2>
                    <p className="muted">
                        {data.crew.group_label}
                        {data.crew.pilot_role
                            ? ` · ${data.crew.pilot_role}`
                            : ''}
                        {data.crew.position ? ` · ${data.crew.position}` : ''}
                    </p>
                    {data.crew.category === 'Pilot' && (
                        <div
                            className="training-crew-aircraft-inline"
                            aria-label="Pesawat crew"
                        >
                            {data.crew.aircrafts.map((aircraft) => (
                                <span
                                    className="training-aircraft-chip"
                                    key={aircraft.id}
                                >
                                    {aircraft.label}
                                    {data.can.manage && (
                                        <button
                                            type="button"
                                            className="btn btn-danger"
                                            aria-label={`Hapus ${aircraft.label} dari crew`}
                                            title={`Hapus ${aircraft.label} dari crew`}
                                            disabled={
                                                aircraftBusy ||
                                                data.crew.aircrafts.length <= 1
                                            }
                                            onClick={() =>
                                                void removeAircraftFromCrew(
                                                    aircraft.id,
                                                    aircraft.label,
                                                )
                                            }
                                        >
                                            Hapus
                                        </button>
                                    )}
                                </span>
                            ))}
                            {data.can.manage && (
                                <div className="training-crew-aircraft-add">
                                    <label className="field">
                                        <span className="sr-only">
                                            Tambah pesawat
                                        </span>
                                        <select
                                            value={selectedAircraftId}
                                            onChange={(event) =>
                                                setSelectedAircraftId(
                                                    event.target.value,
                                                )
                                            }
                                            disabled={
                                                aircraftBusy ||
                                                data.aircraft_options.every(
                                                    (aircraft) =>
                                                        data.crew.aircrafts.some(
                                                            (assigned) =>
                                                                assigned.id ===
                                                                aircraft.id,
                                                        ),
                                                )
                                            }
                                            aria-label="Pilih pesawat untuk ditambahkan"
                                        >
                                            <option value="">
                                                Pilih pesawat
                                            </option>
                                            {data.aircraft_options
                                                .filter(
                                                    (aircraft) =>
                                                        !data.crew.aircrafts.some(
                                                            (assigned) =>
                                                                assigned.id ===
                                                                aircraft.id,
                                                        ),
                                                )
                                                .map((aircraft) => (
                                                    <option
                                                        key={aircraft.id}
                                                        value={aircraft.id}
                                                    >
                                                        {aircraft.label}
                                                    </option>
                                                ))}
                                        </select>
                                    </label>
                                    <button
                                        type="button"
                                        className="btn btn-primary"
                                        disabled={
                                            aircraftBusy || !selectedAircraftId
                                        }
                                        onClick={() => void addAircraftToCrew()}
                                    >
                                        {aircraftBusy ? 'Menyimpan…' : 'Tambah'}
                                    </button>
                                </div>
                            )}
                        </div>
                    )}
                    <div className="training-counts training-counts-start">
                        <span className="badge badge-expired">
                            {data.counts.expired} expired
                        </span>
                        <span className="badge badge-soon">
                            {data.counts.soon} segera
                        </span>
                        <span className="badge badge-valid">
                            {data.counts.valid} berlaku
                        </span>
                    </div>
                </div>
                {data.can.manage && (
                    <div className="training-actions">
                        <Link
                            to={`/training/crews/${crewId}/edit`}
                            className="btn btn-ghost"
                        >
                            Edit data crew
                        </Link>
                        <button
                            type="button"
                            className="btn btn-danger"
                            onClick={() => void remove()}
                        >
                            Hapus
                        </button>
                    </div>
                )}
            </header>

            {error && <Alert>{error}</Alert>}
            <LicenseDocuments
                crewId={crewId}
                documents={data.license_documents}
                manage={data.can.manage}
                onUpdated={() => void load()}
            />
            {data.personal && (
                <section className="panel training-personal">
                    <h3>Data pribadi</h3>
                    <dl>
                        <div>
                            <dt>Tanggal lahir</dt>
                            <dd>{formatDate(data.personal.dob)}</dd>
                        </div>
                        <div>
                            <dt>Kewarganegaraan</dt>
                            <dd>{data.personal.nationality || '–'}</dd>
                        </div>
                        <div>
                            <dt>Email login</dt>
                            <dd>
                                {data.personal.email || 'Belum ada akun User'}
                            </dd>
                        </div>
                        <div>
                            <dt>Bergabung di IAT</dt>
                            <dd>{formatDate(data.personal.joined_iat)}</dd>
                        </div>
                        <div>
                            <dt>Lisensi / rating</dt>
                            <dd>
                                {[data.personal.licence, data.personal.rating]
                                    .filter(Boolean)
                                    .join(' · ') || '–'}
                            </dd>
                        </div>
                        <div>
                            <dt>Paspor</dt>
                            <dd>{data.personal.passport_no || '–'}</dd>
                        </div>
                        <div>
                            <dt>Paspor berlaku sampai</dt>
                            <dd>{formatDate(data.personal.passport_expiry)}</dd>
                        </div>
                        <div>
                            <dt>Alamat / telepon</dt>
                            <dd>{data.personal.address || '–'}</dd>
                        </div>
                        <div>
                            <dt>Catatan</dt>
                            <dd>{data.personal.remarks || '–'}</dd>
                        </div>
                    </dl>
                </section>
            )}

            {data.sections.map((section) => (
                <section className="training-section" key={section.section}>
                    <h3>{section.section}</h3>
                    <div className="table-wrap">
                        <table className="training-table table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Last check</th>
                                    <th>Next check</th>
                                    <th>Plan check</th>
                                    <th>Status</th>
                                    <th />
                                </tr>
                            </thead>
                            <tbody>
                                {section.items.map((record) => (
                                    <RecordRow
                                        key={record.type_id}
                                        crewId={crewId}
                                        record={record}
                                        manage={data.can.manage}
                                        onSaved={() => void load()}
                                    />
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            ))}
        </section>
    );
}
