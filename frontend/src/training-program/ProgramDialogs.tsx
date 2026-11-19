import { useEffect, useMemo, useState, type FormEvent, type ReactNode } from 'react';
import { Alert } from '../components/common/Feedback';
import { trainingProgramService } from '../services/training-program.service';
import { errorMessage } from '../utils/error';
import {
    MONTHS,
    fmtDate,
    type CrewOpt,
    type ProgramRow,
} from './program';

interface DialogProps {
    title: string;
    onClose: () => void;
    children: ReactNode;
}

function Modal({ title, onClose, children }: DialogProps) {
    useEffect(() => {
        function closeOnEscape(event: KeyboardEvent): void {
            if (event.key === 'Escape') {
                onClose();
            }
        }

        window.addEventListener('keydown', closeOnEscape);
        return () => window.removeEventListener('keydown', closeOnEscape);
    }, [onClose]);

    return (
        <div
            className="training-program-backdrop"
            onMouseDown={(event) => {
                if (event.target === event.currentTarget) {
                    onClose();
                }
            }}
        >
            <section
                role="dialog"
                aria-modal="true"
                aria-label={title}
                className="training-program-dialog"
            >
                <header>
                    <h3>{title}</h3>
                    <button
                        type="button"
                        aria-label="Tutup"
                        onClick={onClose}
                    >
                        ×
                    </button>
                </header>
                <div className="training-program-dialog-body">{children}</div>
            </section>
        </div>
    );
}

function DialogError({ error }: { error: string | null }) {
    return error ? <Alert>{error}</Alert> : null;
}

const PLAN_STATUS_LABELS = {
    completed: 'Terlaksana',
    pending: 'Belum terlaksana',
    missed: 'Terlewat',
    not_executed: 'Tidak terlaksana',
} satisfies Record<ProgramRow['plans'][number]['status'], string>;

export function PlanDialog({
    program,
    month,
    year,
    crews,
    manage,
    onClose,
    onChanged,
}: {
    program: ProgramRow;
    month: number;
    year: number;
    crews: CrewOpt[];
    manage: boolean;
    onClose: () => void;
    onChanged: (message: string, closeDialog?: boolean) => Promise<void>;
}) {
    const [selectedCrew, setSelectedCrew] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [notice, setNotice] = useState<string | null>(null);
    const [remarkDrafts, setRemarkDrafts] = useState<Record<number, string>>({});
    const [notExecutedDrafts, setNotExecutedDrafts] = useState<
        Record<number, boolean>
    >({});
    const rows = program.plans.filter((plan) => plan.month === month);
    const planned = new Set(program.plans.map((plan) => plan.crew_id));

    async function updatePlan(
        action: () => Promise<{ message: string }>,
        keepOpen = false,
    ) {
        setBusy(true);
        setError(null);
        setNotice(null);
        try {
            const result = await action();
            if (keepOpen) {
                setNotice(result.message);
                await onChanged(result.message, false);
            } else {
                await onChanged(result.message);
            }
        } catch (cause) {
            setError(errorMessage(cause));
        } finally {
            setBusy(false);
        }
    }

    return (
        <Modal
            title={`Plan · ${program.name} · ${MONTHS[month - 1]} ${year}`}
            onClose={onClose}
        >
            <DialogError error={error} />
            {notice && <Alert kind="success">{notice}</Alert>}
            <p className="training-program-dialog-note">
                Plan disusun dari last check training sebelumnya. Jatuh tempo
                dihitung dari next check atau last check ditambah masa berlaku.
            </p>
            {rows.length === 0 ? (
                <p className="muted">Belum ada crew di plan bulan ini.</p>
            ) : (
                <ul className="training-program-plan-list">
                    {rows.map((plan) => {
                        const notExecuted =
                            notExecutedDrafts[plan.id] ?? plan.not_executed;
                        const remarks =
                            remarkDrafts[plan.id] ?? plan.remarks ?? '';
                        const hasChanges =
                            notExecuted !== plan.not_executed ||
                            remarks !== (plan.remarks ?? '');

                        return (
                            <li key={plan.id}>
                                <div className="training-program-plan-detail">
                                    <strong>{plan.name}</strong>
                                    <span
                                        className={`training-program-status ${plan.status}`}
                                    >
                                        {PLAN_STATUS_LABELS[plan.status]}
                                    </span>
                                    <p>
                                        Jatuh tempo {fmtDate(plan.due_date)}
                                        {plan.overdue &&
                                            ` · sudah lewat sebelum ${year}`}
                                    </p>
                                    {!plan.done && manage && (
                                        <div className="training-program-remark-editor">
                                            <label className="training-program-cancel-toggle">
                                                <input
                                                    type="checkbox"
                                                    checked={notExecuted}
                                                    disabled={busy}
                                                    onChange={(event) =>
                                                        setNotExecutedDrafts(
                                                            (drafts) => ({
                                                                ...drafts,
                                                                [plan.id]:
                                                                    event.target
                                                                        .checked,
                                                            }),
                                                        )
                                                    }
                                                />
                                                Tandai tidak terlaksana
                                            </label>
                                            {notExecuted && (
                                                <>
                                                    <label
                                                        htmlFor={`plan-remarks-${plan.id}`}
                                                    >
                                                        Alasan tidak terlaksana
                                                    </label>
                                                    <textarea
                                                        id={`plan-remarks-${plan.id}`}
                                                        rows={2}
                                                        maxLength={1000}
                                                        value={remarks}
                                                        disabled={busy}
                                                        onChange={(event) =>
                                                            setRemarkDrafts(
                                                                (drafts) => ({
                                                                    ...drafts,
                                                                    [plan.id]:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                }),
                                                            )
                                                        }
                                                    />
                                                </>
                                            )}
                                            <button
                                                type="button"
                                                className="btn btn-ghost"
                                                disabled={
                                                    busy ||
                                                    !hasChanges ||
                                                    (notExecuted &&
                                                        remarks.trim() === '')
                                                }
                                                onClick={() =>
                                                    void updatePlan(
                                                        () =>
                                                            trainingProgramService.updatePlan(
                                                                plan.id,
                                                                {
                                                                    month: plan.month,
                                                                    not_executed:
                                                                        notExecuted,
                                                                    remarks,
                                                                },
                                                            ),
                                                        true,
                                                    )
                                                }
                                            >
                                                Simpan status
                                            </button>
                                        </div>
                                    )}
                                    {plan.remarks &&
                                        (!manage || plan.done) && (
                                            <p className="training-program-remark">
                                                Alasan sebelumnya: {plan.remarks}
                                            </p>
                                        )}
                                </div>
                                {manage && (
                                    <div className="training-program-plan-actions">
                                        <select
                                            aria-label={`Pindah ${plan.name}`}
                                            value={plan.month}
                                            disabled={busy}
                                            onChange={(event) =>
                                                void updatePlan(() =>
                                                    trainingProgramService.updatePlan(
                                                        plan.id,
                                                        {
                                                            month: Number(
                                                                event.target
                                                                    .value,
                                                            ),
                                                        },
                                                    ),
                                                )
                                            }
                                        >
                                            {MONTHS.map((label, index) => (
                                                <option
                                                    key={label}
                                                    value={index + 1}
                                                >
                                                    {label}
                                                </option>
                                            ))}
                                        </select>
                                        <button
                                            type="button"
                                            className="training-program-danger-link"
                                            disabled={busy}
                                            onClick={() =>
                                                void updatePlan(() =>
                                                    trainingProgramService.removePlan(
                                                        plan.id,
                                                    ),
                                                )
                                            }
                                        >
                                            Hapus
                                        </button>
                                    </div>
                                )}
                            </li>
                        );
                    })}
                </ul>
            )}
            {manage && (
                <form
                    className="training-program-add-plan"
                    onSubmit={(event: FormEvent<HTMLFormElement>) => {
                        event.preventDefault();
                        const crewId = Number(selectedCrew);
                        if (!crewId) {
                            return;
                        }
                        void updatePlan(() =>
                            trainingProgramService.storePlan(program.id, {
                                year,
                                month,
                                crew_id: crewId,
                            }),
                        );
                        setSelectedCrew('');
                    }}
                >
                    <select
                        aria-label="Tambah crew ke plan"
                        value={selectedCrew}
                        onChange={(event) =>
                            setSelectedCrew(event.target.value)
                        }
                    >
                        <option value="">Tambah crew ke plan bulan ini…</option>
                        {crews
                            .filter((crew) => !planned.has(crew.id))
                            .map((crew) => (
                                <option key={crew.id} value={crew.id}>
                                    {crew.name} – {crew.group_label}
                                </option>
                            ))}
                    </select>
                    <button
                        type="submit"
                        className="btn btn-primary"
                        disabled={busy || !selectedCrew}
                    >
                        Tambah
                    </button>
                </form>
            )}
        </Modal>
    );
}

interface ActualDialogProps {
    program: ProgramRow;
    month: number;
    year: number;
    crews: CrewOpt[];
    now: { year: number; month: number };
    manage: boolean;
    onClose: () => void;
    onChanged: (message: string) => Promise<void>;
}

export function ActualDialog(props: ActualDialogProps) {
    const actual = props.program.actuals.find(
        (item) => item.month === props.month,
    );

    if (!props.manage) {
        return (
            <Modal
                title={`Implementation · ${props.program.name} · ${MONTHS[props.month - 1]} ${props.year}`}
                onClose={props.onClose}
            >
                {actual ? (
                    <dl className="training-program-actual-details">
                        <div>
                            <dt>Tanggal</dt>
                            <dd>
                                {fmtDate(actual.held_on)}
                                {actual.end_on &&
                                    ` – ${fmtDate(actual.end_on)}`}
                            </dd>
                        </div>
                        {actual.venue && (
                            <div>
                                <dt>Tempat</dt>
                                <dd>{actual.venue}</dd>
                            </div>
                        )}
                        <div>
                            <dt>Peserta ({actual.crews.length})</dt>
                            <dd>
                                {actual.crews.map((crew) => crew.name).join(', ')}
                            </dd>
                        </div>
                        {actual.notes && (
                            <div>
                                <dt>Catatan</dt>
                                <dd>{actual.notes}</dd>
                            </div>
                        )}
                    </dl>
                ) : (
                    <p className="muted">Belum ada realisasi.</p>
                )}
            </Modal>
        );
    }

    return (
        <ActualForm
            {...props}
            actual={actual ?? null}
        />
    );
}

function ActualForm({
    program,
    month,
    year,
    crews,
    now,
    actual,
    onClose,
    onChanged,
}: ActualDialogProps & {
    actual: ProgramRow['actuals'][number] | null;
}) {
    const today = new Date();
    const defaultDate =
        year === now.year && month === now.month
            ? `${year}-${String(month).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`
            : `${year}-${String(month).padStart(2, '0')}-01`;
    const planRows = program.plans;
    const names = useMemo(() => {
        const crewNames = new Map<number, string>();
        crews.forEach((crew) => crewNames.set(crew.id, crew.name));
        planRows.forEach((plan) => crewNames.set(plan.crew_id, plan.name));
        actual?.crews.forEach((crew) => crewNames.set(crew.id, crew.name));
        return crewNames;
    }, [actual, crews, planRows]);
    const initialCrewIds = useMemo(
        () =>
            actual
                ? actual.crews.map((crew) => crew.id)
                : planRows
                      .filter((plan) => plan.month === month && !plan.done)
                      .map((plan) => plan.crew_id),
        [actual, month, planRows],
    );
    const [heldOn, setHeldOn] = useState(actual?.held_on ?? defaultDate);
    const [endOn, setEndOn] = useState(actual?.end_on ?? '');
    const [venue, setVenue] = useState(actual?.venue ?? '');
    const [notes, setNotes] = useState(actual?.notes ?? '');
    const [crewIds, setCrewIds] = useState<number[]>(initialCrewIds);
    const [extraIds, setExtraIds] = useState<number[]>([]);
    const [updateRecords, setUpdateRecords] = useState(true);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const list = [...new Set([...planRows.map((plan) => plan.crew_id), ...(actual?.crews.map((crew) => crew.id) ?? []), ...extraIds])];

    function toggleCrew(id: number): void {
        setCrewIds((ids) =>
            ids.includes(id) ? ids.filter((item) => item !== id) : [...ids, id],
        );
    }

    async function save(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        setBusy(true);
        setError(null);
        try {
            const result = await trainingProgramService.saveActual(
                program.id,
                year,
                month,
                {
                    held_on: heldOn,
                    end_on: endOn,
                    venue,
                    notes,
                    crew_ids: crewIds,
                    update_records: updateRecords,
                },
            );
            await onChanged(result.message);
        } catch (cause) {
            setError(errorMessage(cause));
        } finally {
            setBusy(false);
        }
    }

    async function remove(): Promise<void> {
        if (
            !actual ||
            !window.confirm(
                'Hapus realisasi ini? Last check crew yang sudah diperbarui tidak dikembalikan.',
            )
        ) {
            return;
        }

        setBusy(true);
        setError(null);
        try {
            const result = await trainingProgramService.removeActual(actual.id);
            await onChanged(result.message);
        } catch (cause) {
            setError(errorMessage(cause));
        } finally {
            setBusy(false);
        }
    }

    return (
        <Modal
            title={`Implementation · ${program.name} · ${MONTHS[month - 1]} ${year}`}
            onClose={onClose}
        >
            <form
                className="training-program-form"
                onSubmit={(event) => void save(event)}
            >
                <DialogError error={error} />
                <div className="training-program-form-grid">
                    <label>
                        Tanggal pelaksanaan
                        <input
                            type="date"
                            value={heldOn}
                            onChange={(event) => setHeldOn(event.target.value)}
                            required
                        />
                    </label>
                    <label>
                        Sampai tanggal <span>(opsional)</span>
                        <input
                            type="date"
                            value={endOn}
                            onChange={(event) => setEndOn(event.target.value)}
                        />
                    </label>
                </div>
                <label>
                    Tempat / penyelenggara
                    <input
                        value={venue}
                        onChange={(event) => setVenue(event.target.value)}
                        maxLength={150}
                        placeholder="HELISIM, Halim, ..."
                    />
                </label>
                <fieldset>
                    <legend>Peserta ({crewIds.length} dipilih)</legend>
                    <ul className="training-program-crew-list">
                        {list.length === 0 && (
                            <li>Belum ada crew di plan.</li>
                        )}
                        {list.map((id) => {
                            const planMonth = planRows.find(
                                (plan) => plan.crew_id === id,
                            )?.month;
                            return (
                                <li key={id}>
                                    <label>
                                        <input
                                            type="checkbox"
                                            checked={crewIds.includes(id)}
                                            onChange={() => toggleCrew(id)}
                                        />
                                        <span>{names.get(id) ?? `#${id}`}</span>
                                        {planMonth && (
                                            <span className="muted">
                                                Plan {MONTHS[planMonth - 1]}
                                            </span>
                                        )}
                                    </label>
                                </li>
                            );
                        })}
                    </ul>
                    <select
                        aria-label="Tambah peserta lain"
                        value=""
                        onChange={(event) => {
                            const id = Number(event.target.value);
                            if (id) {
                                setExtraIds((ids) => [...ids, id]);
                                setCrewIds((ids) => [...ids, id]);
                            }
                        }}
                    >
                        <option value="">Tambah peserta lain…</option>
                        {crews
                            .filter((crew) => !list.includes(crew.id))
                            .map((crew) => (
                                <option key={crew.id} value={crew.id}>
                                    {crew.name} – {crew.group_label}
                                </option>
                            ))}
                    </select>
                </fieldset>
                <label>
                    Catatan
                    <textarea
                        rows={2}
                        value={notes}
                        onChange={(event) => setNotes(event.target.value)}
                        maxLength={1000}
                    />
                </label>
                <label className="training-program-checkbox">
                    <input
                        type="checkbox"
                        checked={updateRecords}
                        onChange={(event) =>
                            setUpdateRecords(event.target.checked)
                        }
                    />
                    <span>
                        Perbarui last check dan next check peserta di Training
                        Record berdasarkan tanggal pelaksanaan dan masa berlaku.
                    </span>
                </label>
                <div className="training-program-dialog-actions">
                    <button
                        type="submit"
                        className="btn btn-primary"
                        disabled={busy || !heldOn}
                    >
                        Simpan realisasi
                    </button>
                    {actual && (
                        <button
                            type="button"
                            className="btn btn-danger"
                            disabled={busy}
                            onClick={() => void remove()}
                        >
                            Hapus realisasi
                        </button>
                    )}
                </div>
            </form>
        </Modal>
    );
}

export function ProgramFormDialog({
    program,
    categories,
    trainingTypes,
    onClose,
    onChanged,
}: {
    program: ProgramRow | null;
    categories: Record<string, string>;
    trainingTypes: { id: number; label: string }[];
    onClose: () => void;
    onChanged: (message: string) => Promise<void>;
}) {
    const [filter, setFilter] = useState('');
    const [name, setName] = useState(program?.name ?? '');
    const [category, setCategory] = useState(
        program?.category ?? Object.keys(categories)[0] ?? '',
    );
    const [typeIds, setTypeIds] = useState<number[]>(
        program?.type_ids ?? [],
    );
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const shownTypes = trainingTypes.filter((type) =>
        type.label.toLowerCase().includes(filter.toLowerCase()),
    );

    function toggleType(id: number): void {
        setTypeIds((ids) =>
            ids.includes(id) ? ids.filter((item) => item !== id) : [...ids, id],
        );
    }

    async function save(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        setBusy(true);
        setError(null);
        try {
            const payload = { name: name.trim(), category, type_ids: typeIds };
            const result = program
                ? await trainingProgramService.updateProgram(
                      program.id,
                      payload,
                  )
                : await trainingProgramService.storeProgram(payload);
            await onChanged(result.message);
        } catch (cause) {
            setError(errorMessage(cause));
        } finally {
            setBusy(false);
        }
    }

    async function remove(): Promise<void> {
        if (
            !program ||
            !window.confirm(
                `Hapus program "${program.name}" beserta plan dan realisasinya?`,
            )
        ) {
            return;
        }

        setBusy(true);
        setError(null);
        try {
            const result = await trainingProgramService.removeProgram(
                program.id,
            );
            await onChanged(result.message);
        } catch (cause) {
            setError(errorMessage(cause));
        } finally {
            setBusy(false);
        }
    }

    return (
        <Modal
            title={program ? 'Ubah program' : 'Tambah program'}
            onClose={onClose}
        >
            <form
                className="training-program-form"
                onSubmit={(event) => void save(event)}
            >
                <DialogError error={error} />
                <label>
                    Nama program
                    <input
                        value={name}
                        onChange={(event) => setName(event.target.value)}
                        maxLength={150}
                        required
                    />
                </label>
                <label>
                    Kelompok
                    <select
                        value={category}
                        onChange={(event) => setCategory(event.target.value)}
                    >
                        {Object.entries(categories).map(([key, label]) => (
                            <option key={key} value={key}>
                                {label}
                            </option>
                        ))}
                    </select>
                </label>
                <fieldset>
                    <legend>
                        Sumber plan: item training ({typeIds.length} dipilih)
                    </legend>
                    <p className="training-program-dialog-note">
                        Crew yang memiliki item terpilih otomatis masuk plan
                        sesuai jatuh temponya. Kosongkan jika plan diisi manual.
                    </p>
                    <input
                        type="search"
                        placeholder="Cari item training…"
                        value={filter}
                        onChange={(event) => setFilter(event.target.value)}
                    />
                    <ul className="training-program-crew-list">
                        {shownTypes.map((type) => (
                            <li key={type.id}>
                                <label>
                                    <input
                                        type="checkbox"
                                        checked={typeIds.includes(type.id)}
                                        onChange={() => toggleType(type.id)}
                                    />
                                    {type.label}
                                </label>
                            </li>
                        ))}
                        {shownTypes.length === 0 && (
                            <li>Tidak ada item yang cocok.</li>
                        )}
                    </ul>
                </fieldset>
                <div className="training-program-dialog-actions">
                    <button
                        type="submit"
                        className="btn btn-primary"
                        disabled={busy || !name.trim()}
                    >
                        Simpan
                    </button>
                    {program && (
                        <button
                            type="button"
                            className="btn btn-danger"
                            disabled={busy}
                            onClick={() => void remove()}
                        >
                            Hapus program
                        </button>
                    )}
                </div>
            </form>
        </Modal>
    );
}
