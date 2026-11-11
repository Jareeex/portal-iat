import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Alert, Loader } from '../components/common/Feedback';
import { errorMessage } from '../utils/error';
import { trainingProgramService } from '../services/training-program.service';
import {
    MONTHS,
    fmtShort,
    type ProgramPageProps,
    type ProgramRow,
} from './program';
import { ActualDialog, PlanDialog, ProgramFormDialog } from './ProgramDialogs';

type OpenDialog =
    | { kind: 'plan'; programId: number; month: number }
    | { kind: 'actual'; programId: number; month: number }
    | { kind: 'program'; programId: number | null }
    | null;

function Stat({
    value,
    total,
    label,
}: {
    value: number;
    total: number;
    label: string;
}) {
    return (
        <div className="training-program-stat">
            <strong>
                {value} <span>/ {total}</span>
            </strong>
            <span>{label}</span>
        </div>
    );
}

export default function Program() {
    const [searchParams, setSearchParams] = useSearchParams();
    const year = Number(searchParams.get('year')) || new Date().getFullYear();
    const [data, setData] = useState<ProgramPageProps | null>(null);
    const [category, setCategory] = useState('all');
    const [open, setOpen] = useState<OpenDialog>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [message, setMessage] = useState<string | null>(null);

    const load = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            setData(await trainingProgramService.index(year));
        } catch (cause) {
            setError(errorMessage(cause));
        } finally {
            setLoading(false);
        }
    }, [year]);

    useEffect(() => {
        void load();
    }, [load]);

    const groups = useMemo(
        () =>
            Object.entries(data?.categories ?? {})
                .map(([key, label]) => ({
                    key,
                    label,
                    rows: (data?.programs ?? []).filter(
                        (program) => program.category === key,
                    ),
                }))
                .filter(
                    (group) =>
                        (category === 'all' || group.key === category) &&
                        group.rows.length > 0,
                ),
        [category, data],
    );

    const isPast = (month: number) =>
        year < (data?.now.year ?? year) ||
        (year === data?.now.year && month < (data?.now.month ?? month));
    const currentProgram = (id: number | null) =>
        data?.programs.find((program) => program.id === id) ?? null;
    const close = () => setOpen(null);

    async function generatePlan(): Promise<void> {
        if (
            !window.confirm(
                `Sinkronkan plan ${year} dari next check training? Plan yang sudah ada dapat diperbarui.`,
            )
        ) {
            return;
        }

        setMessage(null);
        try {
            const result = await trainingProgramService.generatePlan(year);
            setMessage(result.message);
            await load();
        } catch (cause) {
            setError(errorMessage(cause));
        }
    }

    async function onChanged(nextMessage: string): Promise<void> {
        setOpen(null);
        setMessage(nextMessage);
        await load();
    }

    if (loading && !data) {
        return <Loader />;
    }

    if (!data) {
        return (
            <section className="training-program-page">
                <header className="page-head">
                    <h2>Ops Training Program</h2>
                </header>
                {error && <Alert>{error}</Alert>}
            </section>
        );
    }

    const dialogProgram =
        open && open.kind !== 'program'
            ? currentProgram(open.programId)
            : null;

    return (
        <section className="training-program-page">
            <header className="page-head page-head-row">
                <div>
                    <Link
                        to="/training"
                        className="training-program-back-link"
                    >
                        ← Training Record
                    </Link>
                    <span className="eyebrow training-eyebrow">
                        TRAINING DEPARTMENT
                    </span>
                    <h2>Ops Training Program {data.year}</h2>
                    <p className="muted">
                        PT. Indonesia Air Transport · Training Department
                        {!data.can.manage && ' · Hanya lihat'}
                    </p>
                </div>
                <div className="training-program-actions">
                    <label className="training-program-year">
                        Tahun
                        <select
                            value={data.year}
                            onChange={(event) =>
                                setSearchParams({ year: event.target.value })
                            }
                        >
                            {data.years.map((option) => (
                                <option key={option} value={option}>
                                    {option}
                                </option>
                            ))}
                        </select>
                    </label>
                    {data.can.manage && (
                        <>
                            <button
                                type="button"
                                className="btn btn-ghost"
                                onClick={() => void generatePlan()}
                            >
                                Susun plan dari last check
                            </button>
                            <button
                                type="button"
                                className="btn btn-primary"
                                onClick={() =>
                                    setOpen({
                                        kind: 'program',
                                        programId: null,
                                    })
                                }
                            >
                                + Tambah program
                            </button>
                        </>
                    )}
                </div>
            </header>

            {error && <Alert>{error}</Alert>}
            {message && <Alert kind="success">{message}</Alert>}

            <div className="training-program-stats">
                <Stat
                    value={data.summary.cells_done}
                    total={data.summary.cells}
                    label="bulan plan yang sudah terlaksana"
                />
                <Stat
                    value={data.summary.crew_done}
                    total={data.summary.crew}
                    label="crew terplan yang sudah ikut"
                />
            </div>

            <div className="training-program-legend">
                <div className="training-program-categories">
                    <button
                        type="button"
                        className={
                            category === 'all'
                                ? 'training-program-chip active'
                                : 'training-program-chip'
                        }
                        onClick={() => setCategory('all')}
                    >
                        Semua
                    </button>
                    {Object.entries(data.categories).map(([key, label]) => (
                        <button
                            key={key}
                            type="button"
                            className={
                                category === key
                                    ? 'training-program-chip active'
                                    : 'training-program-chip'
                            }
                            onClick={() => setCategory(key)}
                        >
                            {label}
                        </button>
                    ))}
                </div>
                <ul>
                    <li>
                        <span className="training-program-key plan" />
                        Plan · terlaksana/terplan
                    </li>
                    <li>
                        <span className="training-program-key actual" />
                        Actual / Implementation
                    </li>
                    <li>
                        <span className="training-program-key overdue" />
                        Terlambat · bulan lewat, belum ikut
                    </li>
                </ul>
            </div>

            <div className="training-program-table-wrap">
                <table className="training-program-table">
                    <colgroup>
                        <col className="training-program-number-col" />
                        <col className="training-program-name-col" />
                        {MONTHS.map((month) => (
                            <col key={month} />
                        ))}
                    </colgroup>
                    <thead>
                        <tr>
                            <th>NO</th>
                            <th>TRAINING PROGRAM</th>
                            {MONTHS.map((month) => (
                                <th key={month}>{month}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {groups.length === 0 && (
                            <tr>
                                <td colSpan={14} className="training-program-empty">
                                    Belum ada program.{' '}
                                    {data.can.manage &&
                                        'Jalankan seeder atau tambahkan program baru.'}
                                </td>
                            </tr>
                        )}
                        {groups.map((group) => (
                            <ProgramGroup
                                key={group.key}
                                label={group.label}
                                programs={group.rows}
                                canManage={data.can.manage}
                                isPast={isPast}
                                onOpenPlan={(programId, month) =>
                                    setOpen({
                                        kind: 'plan',
                                        programId,
                                        month,
                                    })
                                }
                                onOpenActual={(programId, month) =>
                                    setOpen({
                                        kind: 'actual',
                                        programId,
                                        month,
                                    })
                                }
                                onEditProgram={(programId) =>
                                    setOpen({
                                        kind: 'program',
                                        programId,
                                    })
                                }
                            />
                        ))}
                    </tbody>
                </table>
            </div>

            <p className="training-program-note">
                Plan disusun dari tanggal pemeriksaan training terakhir dan masa
                berlaku. Klik sel kuning untuk melihat crew, atau sel biru untuk
                realisasi. Sel kosong dapat digunakan untuk menambahkan plan atau
                realisasi.
            </p>

            {open?.kind === 'plan' && dialogProgram && (
                <PlanDialog
                    program={dialogProgram}
                    month={open.month}
                    year={data.year}
                    crews={data.crews}
                    manage={data.can.manage}
                    onClose={close}
                    onChanged={onChanged}
                />
            )}
            {open?.kind === 'actual' && dialogProgram && (
                <ActualDialog
                    program={dialogProgram}
                    month={open.month}
                    year={data.year}
                    crews={data.crews}
                    now={data.now}
                    manage={data.can.manage}
                    onClose={close}
                    onChanged={onChanged}
                />
            )}
            {open?.kind === 'program' && data.can.manage && (
                <ProgramFormDialog
                    program={currentProgram(open.programId)}
                    categories={data.categories}
                    trainingTypes={data.trainingTypes}
                    onClose={close}
                    onChanged={onChanged}
                />
            )}
        </section>
    );
}

function ProgramGroup({
    label,
    programs,
    canManage,
    isPast,
    onOpenPlan,
    onOpenActual,
    onEditProgram,
}: {
    label: string;
    programs: ProgramRow[];
    canManage: boolean;
    isPast: (month: number) => boolean;
    onOpenPlan: (programId: number, month: number) => void;
    onOpenActual: (programId: number, month: number) => void;
    onEditProgram: (programId: number) => void;
}) {
    return (
        <>
            <tr className="training-program-group-row">
                <td />
                <td>{label}</td>
                <td colSpan={12}>{label}</td>
            </tr>
            {programs.map((program, index) => (
                <ProgramRows
                    key={program.id}
                    program={program}
                    index={index}
                    canManage={canManage}
                    isPast={isPast}
                    onOpenPlan={onOpenPlan}
                    onOpenActual={onOpenActual}
                    onEditProgram={onEditProgram}
                />
            ))}
        </>
    );
}

function ProgramRows({
    program,
    index,
    canManage,
    isPast,
    onOpenPlan,
    onOpenActual,
    onEditProgram,
}: {
    program: ProgramRow;
    index: number;
    canManage: boolean;
    isPast: (month: number) => boolean;
    onOpenPlan: (programId: number, month: number) => void;
    onOpenActual: (programId: number, month: number) => void;
    onEditProgram: (programId: number) => void;
}) {
    return (
        <>
            <tr className="training-program-plan-row">
                <td className="training-program-number">{index + 1}</td>
                <td className="training-program-name">
                    {canManage ? (
                        <button
                            type="button"
                            onClick={() => onEditProgram(program.id)}
                        >
                            {program.name}
                        </button>
                    ) : (
                        program.name
                    )}
                    <span>Plan</span>
                </td>
                {MONTHS.map((_, index) => {
                    const month = index + 1;
                    const plans = program.plans.filter(
                        (plan) => plan.month === month,
                    );
                    const completed = plans.filter((plan) => plan.done).length;
                    const late =
                        isPast(month) && completed < plans.length;

                    return (
                        <td key={month}>
                            {plans.length > 0 ? (
                                <button
                                    type="button"
                                    className={
                                        late
                                            ? 'training-program-cell plan overdue'
                                            : 'training-program-cell plan'
                                    }
                                    aria-label={`Plan ${program.name} ${MONTHS[index]}: ${plans.length} crew, ${completed} sudah ikut`}
                                    onClick={() =>
                                        onOpenPlan(program.id, month)
                                    }
                                >
                                    {completed}/{plans.length}
                                    {late && ' !'}
                                </button>
                            ) : canManage ? (
                                <button
                                    type="button"
                                    className="training-program-cell empty"
                                    aria-label={`Tambah plan ${program.name} ${MONTHS[index]}`}
                                    onClick={() =>
                                        onOpenPlan(program.id, month)
                                    }
                                >
                                    –
                                </button>
                            ) : (
                                <span className="training-program-dash">–</span>
                            )}
                        </td>
                    );
                })}
            </tr>
            <tr className="training-program-actual-row">
                <td />
                <td>Implementation</td>
                {MONTHS.map((_, index) => {
                    const month = index + 1;
                    const actual = program.actuals.find(
                        (item) => item.month === month,
                    );
                    return (
                        <td key={month}>
                            {actual ? (
                                <button
                                    type="button"
                                    className="training-program-cell actual"
                                    aria-label={`Realisasi ${program.name} ${MONTHS[index]}: ${actual.crews.length} peserta`}
                                    onClick={() =>
                                        onOpenActual(program.id, month)
                                    }
                                >
                                    {actual.crews.length} org
                                    <span>{fmtShort(actual.held_on)}</span>
                                </button>
                            ) : canManage ? (
                                <button
                                    type="button"
                                    className="training-program-cell empty"
                                    aria-label={`Input realisasi ${program.name} ${MONTHS[index]}`}
                                    onClick={() =>
                                        onOpenActual(program.id, month)
                                    }
                                >
                                    –
                                </button>
                            ) : (
                                <span className="training-program-dash">–</span>
                            )}
                        </td>
                    );
                })}
            </tr>
        </>
    );
}
