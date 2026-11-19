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

const OUTCOME_SERIES = [
    {
        key: 'completed',
        label: 'Terlaksana',
        color: '#2563eb',
        totalKey: 'crew_done',
    },
    {
        key: 'pending',
        label: 'Belum terlaksana',
        color: '#facc15',
        totalKey: 'crew_pending',
    },
    {
        key: 'missed',
        label: 'Terlewat',
        color: '#f97316',
        totalKey: 'crew_missed',
    },
    {
        key: 'not_executed',
        label: 'Tidak terlaksana',
        color: '#dc2626',
        totalKey: 'crew_not_executed',
    },
] as const;

const PLAN_OUTCOME_ROWS = [
    { status: 'pending', label: 'Belum terlaksana', className: 'pending' },
    { status: 'missed', label: 'Terlewat', className: 'missed' },
    {
        status: 'not_executed',
        label: 'Tidak terlaksana',
        className: 'not-executed',
    },
] as const;

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

function OutcomeCharts({
    year,
    summary,
}: {
    year: number;
    summary: ProgramPageProps['summary'];
}) {
    const total = OUTCOME_SERIES.reduce(
        (sum, outcome) => sum + summary[outcome.totalKey],
        0,
    );
    let pieOffset = 0;
    const pieSegments = OUTCOME_SERIES.map((outcome) => {
        const value = summary[outcome.totalKey];
        const percentage = total > 0 ? (value / total) * 100 : 0;
        const offset = pieOffset;
        pieOffset += percentage;

        return { ...outcome, value, percentage, offset };
    });
    const chartMax = Math.max(
        1,
        ...summary.monthly_crew.map(
            (month) =>
                month.completed +
                month.pending +
                month.missed +
                month.not_executed,
        ),
    );
    const chartBase = 155;
    const chartHeight = 125;

    return (
        <section className="training-program-analytics">
            <div className="training-program-chart">
                <h3>Status keseluruhan {year}</h3>
                <div className="training-program-pie-layout">
                    <svg
                        className="training-program-pie"
                        viewBox="0 0 120 120"
                        role="img"
                        aria-label={`Ringkasan ${year}: ${summary.crew_done} terlaksana, ${summary.crew_pending} belum terlaksana, ${summary.crew_missed} terlewat, ${summary.crew_not_executed} tidak terlaksana`}
                    >
                        <circle
                            cx="60"
                            cy="60"
                            r="43"
                            fill="none"
                            pathLength="100"
                            stroke="#e5e7eb"
                            strokeWidth="18"
                        />
                        {pieSegments.map(
                            (segment) =>
                                segment.value > 0 && (
                                    <circle
                                        key={segment.key}
                                        cx="60"
                                        cy="60"
                                        r="43"
                                        fill="none"
                                        pathLength="100"
                                        stroke={segment.color}
                                        strokeWidth="18"
                                        strokeDasharray={`${segment.percentage} ${100 - segment.percentage}`}
                                        strokeDashoffset={-segment.offset}
                                        transform="rotate(-90 60 60)"
                                    />
                                ),
                        )}
                        <text
                            x="60"
                            y="58"
                            className="training-program-pie-total"
                        >
                            {total}
                        </text>
                        <text
                            x="60"
                            y="74"
                            className="training-program-pie-label"
                        >
                            crew
                        </text>
                    </svg>
                    <ul className="training-program-chart-legend">
                        {pieSegments.map((segment) => (
                            <li key={segment.key}>
                                <span
                                    className={`training-program-key ${segment.key === 'completed' ? 'actual' : segment.key.replace('_', '-')}`}
                                />
                                {segment.label}
                                <strong>{segment.value}</strong>
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
            <div className="training-program-chart">
                <h3>Realisasi per bulan {year}</h3>
                <div className="training-program-bar-wrap">
                    <svg
                        viewBox="0 0 720 195"
                        role="img"
                        aria-label={`Jumlah crew per status setiap bulan tahun ${year}`}
                    >
                        <line
                            x1="20"
                            x2="700"
                            y1={chartBase}
                            y2={chartBase}
                            className="training-program-chart-axis"
                        />
                        {summary.monthly_crew.map((month, index) => {
                            const x = 28 + index * 56;
                            const count = OUTCOME_SERIES.reduce(
                                (sum, outcome) => sum + month[outcome.key],
                                0,
                            );
                            let stackedHeight = 0;
                            const segments = OUTCOME_SERIES.map((outcome) => {
                                const value = month[outcome.key];
                                const height =
                                    (value / chartMax) * chartHeight;
                                const y = chartBase - stackedHeight - height;
                                stackedHeight += height;

                                return { ...outcome, value, height, y };
                            });

                            return (
                                <g key={month.month}>
                                    <title>
                                        {MONTHS[month.month - 1]}:{' '}
                                        {segments
                                            .map(
                                                (segment) =>
                                                    `${segment.value} ${segment.label.toLowerCase()}`,
                                            )
                                            .join(', ')}
                                    </title>
                                    {segments.map(
                                        (segment) =>
                                            segment.value > 0 && (
                                                <rect
                                                    key={segment.key}
                                                    x={x}
                                                    y={segment.y}
                                                    width="30"
                                                    height={segment.height}
                                                    fill={segment.color}
                                                />
                                            ),
                                    )}
                                    {count > 0 && (
                                        <text
                                            x={x + 15}
                                            y={Math.max(
                                                12,
                                                chartBase - stackedHeight - 5,
                                            )}
                                            className="training-program-chart-value"
                                        >
                                            {count}
                                        </text>
                                    )}
                                    <text
                                        x={x + 15}
                                        y="178"
                                        className="training-program-chart-month"
                                    >
                                        {MONTHS[month.month - 1]}
                                    </text>
                                </g>
                            );
                        })}
                    </svg>
                </div>
                <ul className="training-program-chart-legend training-program-bar-legend">
                    {pieSegments.map((segment) => (
                        <li key={segment.key}>
                            <span
                                className={`training-program-key ${segment.key === 'completed' ? 'actual' : segment.key.replace('_', '-')}`}
                            />
                            {segment.label}
                        </li>
                    ))}
                </ul>
            </div>
            <p className="training-program-chart-note">
                Status dihitung per crew pada plan tahun terpilih. Plan yang
                bulannya sudah lewat tetapi belum diikuti dapat dijadwalkan
                ulang; “Tidak terlaksana” ditandai manual beserta alasannya.
            </p>
        </section>
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

    async function onChanged(
        nextMessage: string,
        closeDialog = true,
    ): Promise<void> {
        if (closeDialog) {
            setOpen(null);
        }
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
            <OutcomeCharts year={data.year} summary={data.summary} />

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
                        <span className="training-program-key pending" />
                        Belum terlaksana
                    </li>
                    <li>
                        <span className="training-program-key actual" />
                        Terlaksana / Actual
                    </li>
                    <li>
                        <span className="training-program-key missed" />
                        Terlewat · bulan plan sudah lewat
                    </li>
                    <li>
                        <span className="training-program-key not-executed" />
                        Tidak terlaksana · ditandai manual
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
                berlaku. Plan yang terlewat dapat dipindah ke bulan lain. Untuk
                status “Tidak terlaksana”, tandai manual dan isi alasan. Klik
                sel status untuk melihat crew.
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
    onOpenPlan,
    onOpenActual,
    onEditProgram,
}: {
    label: string;
    programs: ProgramRow[];
    canManage: boolean;
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
    onOpenPlan,
    onOpenActual,
    onEditProgram,
}: {
    program: ProgramRow;
    index: number;
    canManage: boolean;
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
                    const late = plans.some(
                        (plan) => plan.status === 'missed',
                    );

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
            {PLAN_OUTCOME_ROWS.map((outcome) => (
                <tr
                    key={outcome.status}
                    className={`training-program-outcome-row ${outcome.className}`}
                >
                    <td />
                    <td>{outcome.label}</td>
                    {MONTHS.map((_, index) => {
                        const month = index + 1;
                        const count = program.plans.filter(
                            (plan) =>
                                plan.month === month &&
                                plan.status === outcome.status,
                        ).length;

                        return (
                            <td key={month}>
                                {count > 0 ? (
                                    <button
                                        type="button"
                                        className={`training-program-cell ${outcome.className}`}
                                        aria-label={`${outcome.label} ${program.name} ${MONTHS[index]}: ${count} crew`}
                                        onClick={() =>
                                            onOpenPlan(program.id, month)
                                        }
                                    >
                                        {count} org
                                    </button>
                                ) : (
                                    <span className="training-program-dash">–</span>
                                )}
                            </td>
                        );
                    })}
                </tr>
            ))}
        </>
    );
}
