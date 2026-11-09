import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { Alert, Loader } from '../../components/common/Feedback';
import { Field } from '../../components/common/Field';
import { Modal } from '../../components/common/Modal';
import { DataTable, type Column } from '../../components/tables/DataTable';
import { Pagination } from '../../components/tables/Pagination';
import { usePermission } from '../../hooks/usePermission';
import { departmentService } from '../../services/department.service';
import {
    employeeService,
    type JobPositionPayload,
    type EmployeeProfilePayload,
} from '../../services/employee.service';
import { errorMessage } from '../../utils/error';
import type { Department, EmployeeProfile, JobPosition } from '../../types';

interface EmployeeForm {
    name: string;
    employee_number: string;
    job_title: string;
    office: string;
    employment_status: string;
    join_date: string;
    end_date: string;
}

const emptyForm: EmployeeForm = {
    name: '',
    employee_number: '',
    job_title: '',
    office: '',
    employment_status: '',
    join_date: '',
    end_date: '',
};

const employmentStatuses = [
    'Tetap',
    'Kontrak',
    'Probation',
    'Magang',
    'Resign',
];

const emptyPositionForm: JobPositionPayload = {
    name: '',
    sort_order: 1,
    is_active: true,
    is_leave_manager: false,
};

export default function EmployeesPage() {
    const { canEdit, isMaster } = usePermission();
    const canEditEmployees = isMaster || canEdit('hr-employees');
    const [employees, setEmployees] = useState<EmployeeProfile[]>([]);
    const [departments, setDepartments] = useState<Department[]>([]);
    const [positions, setPositions] = useState<JobPosition[]>([]);
    const [page, setPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [total, setTotal] = useState(0);
    const [searchInput, setSearchInput] = useState('');
    const [search, setSearch] = useState('');
    const [departmentId, setDepartmentId] = useState<number | ''>('');
    const [employmentStatus, setEmploymentStatus] = useState('');
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [editing, setEditing] = useState<EmployeeProfile | null>(null);
    const [creating, setCreating] = useState(false);
    const [form, setForm] = useState<EmployeeForm>(emptyForm);
    const [formError, setFormError] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);
    const [positionEditing, setPositionEditing] = useState<JobPosition | null>(
        null,
    );
    const [positionModalOpen, setPositionModalOpen] = useState(false);
    const [positionForm, setPositionForm] =
        useState<JobPositionPayload>(emptyPositionForm);
    const [positionError, setPositionError] = useState<string | null>(null);
    const [positionSaving, setPositionSaving] = useState(false);

    const load = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const result = await employeeService.list({
                page,
                q: search || undefined,
                department_id: departmentId,
                employment_status: employmentStatus || undefined,
            });
            setEmployees(result.data);
            setLastPage(result.last_page);
            setTotal(result.total);
        } catch (loadError) {
            setError(errorMessage(loadError));
        } finally {
            setLoading(false);
        }
    }, [departmentId, employmentStatus, page, search]);

    useEffect(() => {
        void load();
    }, [load]);

    useEffect(() => {
        void departmentService
            .list()
            .then(setDepartments)
            .catch((loadError) => {
                setError(errorMessage(loadError));
            });
    }, []);

    const loadPositions = useCallback(async () => {
        if (!canEditEmployees) return;

        try {
            setPositions(await employeeService.jobPositions());
        } catch (loadError) {
            setError(errorMessage(loadError));
        }
    }, [canEditEmployees]);

    useEffect(() => {
        void loadPositions();
    }, [loadPositions]);

    function openEdit(employee: EmployeeProfile) {
        setCreating(false);
        setEditing(employee);
        setForm({
            name: employee.name,
            employee_number: employee.employee_number ?? '',
            job_title: employee.job_title ?? '',
            office: employee.office ?? '',
            employment_status: employee.employment_status ?? '',
            join_date: employee.join_date ?? '',
            end_date: employee.end_date ?? '',
        });
        setFormError(null);
    }

    function openCreate() {
        setEditing(null);
        setCreating(true);
        setForm(emptyForm);
        setFormError(null);
    }

    async function onSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!editing && !creating) return;

        setSaving(true);
        setFormError(null);
        const payload: EmployeeProfilePayload = {
            employee_number: form.employee_number.trim() || null,
            job_title: form.job_title.trim() || null,
            office: form.office.trim() || null,
            employment_status: form.employment_status || null,
            join_date: form.join_date || null,
            end_date: form.end_date || null,
        };

        try {
            if (editing) {
                await employeeService.update(
                    editing.id,
                    editing.user_id === null
                        ? { ...payload, name: form.name.trim() }
                        : payload,
                );
            } else {
                await employeeService.create({
                    ...payload,
                    name: form.name.trim(),
                });
            }
            setEditing(null);
            setCreating(false);
            await load();
        } catch (saveError) {
            setFormError(errorMessage(saveError));
        } finally {
            setSaving(false);
        }
    }

    function openPosition(position?: JobPosition) {
        setPositionEditing(position ?? null);
        setPositionModalOpen(true);
        setPositionForm(
            position
                ? {
                      name: position.name,
                      sort_order: position.sort_order,
                      is_active: position.is_active,
                      is_leave_manager: position.is_leave_manager,
                  }
                : { ...emptyPositionForm, sort_order: positions.length + 1 },
        );
        setPositionError(null);
    }

    async function onPositionSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setPositionSaving(true);
        setPositionError(null);
        try {
            if (positionEditing) {
                await employeeService.updateJobPosition(
                    positionEditing.id,
                    positionForm,
                );
            } else {
                await employeeService.createJobPosition(positionForm);
            }
            setPositionEditing(null);
            setPositionModalOpen(false);
            await Promise.all([loadPositions(), load()]);
        } catch (saveError) {
            setPositionError(errorMessage(saveError));
        } finally {
            setPositionSaving(false);
        }
    }

    const columns: Column<EmployeeProfile>[] = [
        {
            header: 'Karyawan / akun',
            render: (employee) => (
                <div>
                    <strong>{employee.name}</strong>
                    <div className="muted">
                        {employee.user?.email ?? 'Belum terhubung ke akun'}
                    </div>
                </div>
            ),
        },
        {
            header: 'No. pegawai',
            render: (employee) =>
                employee.employee_number || (
                    <span className="muted">Belum diisi</span>
                ),
        },
        {
            header: 'Jabatan',
            render: (employee) =>
                employee.job_title || (
                    <span className="muted">Belum diisi</span>
                ),
        },
        {
            header: 'Departemen',
            render: (employee) =>
                employee.user?.department?.name ?? (
                    <span className="muted">-</span>
                ),
        },
        {
            header: 'Akun',
            render: (employee) =>
                employee.user ? (
                    <div>
                        <span
                            className={
                                employee.user.role === 'master'
                                    ? 'badge badge-accent'
                                    : 'badge'
                            }
                        >
                            {employee.user.role}
                        </span>{' '}
                        <span
                            className={
                                employee.user.is_active
                                    ? 'badge badge-ok'
                                    : 'badge badge-off'
                            }
                        >
                            {employee.user.is_active ? 'Aktif' : 'Nonaktif'}
                        </span>
                    </div>
                ) : (
                    <span className="badge badge-off">Belum terhubung</span>
                ),
        },
        {
            header: 'Status kerja',
            render: (employee) =>
                employee.employment_status || (
                    <span className="muted">Belum diisi</span>
                ),
        },
        ...(canEditEmployees
            ? [
                  {
                      header: '',
                      width: '100px',
                      render: (employee: EmployeeProfile) => (
                          <div className="row-actions">
                              <button
                                  type="button"
                                  className="btn btn-ghost"
                                  onClick={() => openEdit(employee)}
                              >
                                  Edit
                              </button>
                          </div>
                      ),
                  },
              ]
            : []),
    ];

    return (
        <div>
            <div className="page-head page-head-row">
                <div>
                    <h2>Employees</h2>
                    <p className="muted">
                        HR dapat membuat profil employee; akun dapat dihubungkan
                        oleh Master. Total: {total}
                    </p>
                </div>
                {canEditEmployees && (
                    <button
                        type="button"
                        className="btn btn-primary"
                        onClick={openCreate}
                    >
                        + Tambah employee
                    </button>
                )}
            </div>

            {canEditEmployees && (
                <section className="panel position-panel">
                    <div className="page-head page-head-row">
                        <div>
                            <h3>Struktur jabatan</h3>
                            <p className="muted">
                                Atur urutan jabatan dan siapa yang dapat menjadi
                                approver cuti.
                            </p>
                        </div>
                        <button
                            type="button"
                            className="btn btn-primary"
                            onClick={() => openPosition()}
                        >
                            + Tambah jabatan
                        </button>
                    </div>
                    <DataTable
                        columns={[
                            {
                                header: 'Urutan',
                                render: (position) => position.sort_order,
                            },
                            {
                                header: 'Jabatan',
                                render: (position) => position.name,
                            },
                            {
                                header: 'Status',
                                render: (position) => (
                                    <span
                                        className={
                                            position.is_active
                                                ? 'badge badge-ok'
                                                : 'badge badge-off'
                                        }
                                    >
                                        {position.is_active
                                            ? 'Aktif'
                                            : 'Nonaktif'}
                                    </span>
                                ),
                            },
                            {
                                header: 'Approver cuti',
                                render: (position) =>
                                    position.is_leave_manager ? 'Ya' : 'Tidak',
                            },
                            {
                                header: 'Aksi',
                                width: '90px',
                                render: (position) => (
                                    <button
                                        type="button"
                                        className="btn btn-ghost"
                                        onClick={() => openPosition(position)}
                                    >
                                        Edit
                                    </button>
                                ),
                            },
                        ]}
                        rows={positions}
                        rowKey={(position) => position.id}
                        empty="Jabatan belum tersedia."
                    />
                </section>
            )}

            <form
                className="toolbar"
                onSubmit={(event) => {
                    event.preventDefault();
                    setPage(1);
                    setSearch(searchInput.trim());
                }}
            >
                <input
                    type="search"
                    placeholder="Cari nama, email, no. pegawai, jabatan"
                    value={searchInput}
                    onChange={(event) => setSearchInput(event.target.value)}
                />
                <select
                    value={departmentId}
                    onChange={(event) => {
                        setPage(1);
                        setDepartmentId(
                            event.target.value === ''
                                ? ''
                                : Number(event.target.value),
                        );
                    }}
                >
                    <option value="">Semua departemen</option>
                    {departments.map((department) => (
                        <option key={department.id} value={department.id}>
                            {department.name}
                        </option>
                    ))}
                </select>
                <select
                    value={employmentStatus}
                    onChange={(event) => {
                        setPage(1);
                        setEmploymentStatus(event.target.value);
                    }}
                >
                    <option value="">Semua status kerja</option>
                    {employmentStatuses.map((status) => (
                        <option key={status} value={status}>
                            {status}
                        </option>
                    ))}
                </select>
                <button type="submit" className="btn">
                    Cari
                </button>
            </form>

            {error && <Alert>{error}</Alert>}
            {loading ? (
                <Loader />
            ) : (
                <>
                    <DataTable
                        columns={columns}
                        rows={employees}
                        rowKey={(employee) => employee.id}
                        empty="Belum ada karyawan."
                    />
                    <Pagination
                        page={page}
                        lastPage={lastPage}
                        total={total}
                        onChange={setPage}
                    />
                </>
            )}

            {(editing || creating) && (
                <Modal
                    title={
                        editing
                            ? `Profil karyawan · ${editing.name}`
                            : 'Tambah employee'
                    }
                    onClose={() => {
                        setEditing(null);
                        setCreating(false);
                    }}
                >
                    <form onSubmit={onSubmit} className="form">
                        {formError && <Alert>{formError}</Alert>}
                        {creating || editing?.user_id === null ? (
                            <>
                                <Field label="Nama employee">
                                    <input
                                        value={form.name}
                                        onChange={(event) =>
                                            setForm({
                                                ...form,
                                                name: event.target.value,
                                            })
                                        }
                                        required
                                    />
                                </Field>
                                {creating && (
                                    <p className="muted">
                                        Profil ini belum memiliki akun. Master
                                        dapat menghubungkannya saat membuat akun
                                        di menu Users.
                                    </p>
                                )}
                            </>
                        ) : (
                            <p className="muted">
                                Nama dan akun dikelola di Master Panel → Users.
                            </p>
                        )}
                        <Field label="Nomor pegawai">
                            <input
                                value={form.employee_number}
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        employee_number: event.target.value,
                                    })
                                }
                            />
                        </Field>
                        <Field label="Jabatan">
                            <select
                                value={form.job_title}
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        job_title: event.target.value,
                                    })
                                }
                            >
                                <option value="">Belum ditentukan</option>
                                {positions
                                    .filter(
                                        (position) =>
                                            position.is_active ||
                                            position.name === form.job_title,
                                    )
                                    .map((position) => (
                                        <option
                                            key={position.id}
                                            value={position.name}
                                        >
                                            {position.name}
                                            {position.is_active
                                                ? ''
                                                : ' (nonaktif)'}
                                        </option>
                                    ))}
                            </select>
                        </Field>
                        <Field label="Kantor">
                            <input
                                value={form.office}
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        office: event.target.value,
                                    })
                                }
                            />
                        </Field>
                        <Field label="Status kerja">
                            <select
                                value={form.employment_status}
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        employment_status: event.target.value,
                                    })
                                }
                            >
                                <option value="">Belum ditentukan</option>
                                {employmentStatuses.map((status) => (
                                    <option key={status} value={status}>
                                        {status}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <div className="form-row">
                            <Field label="Tanggal masuk">
                                <input
                                    type="date"
                                    value={form.join_date}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            join_date: event.target.value,
                                        })
                                    }
                                />
                            </Field>
                            <Field label="Tanggal akhir">
                                <input
                                    type="date"
                                    value={form.end_date}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            end_date: event.target.value,
                                        })
                                    }
                                />
                            </Field>
                        </div>
                        <div className="modal-actions">
                            <button
                                type="button"
                                className="btn btn-ghost"
                                onClick={() => setEditing(null)}
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                className="btn btn-primary"
                                disabled={saving}
                            >
                                {saving ? 'Menyimpan...' : 'Simpan profil'}
                            </button>
                        </div>
                    </form>
                </Modal>
            )}
            {positionModalOpen && (
                <Modal
                    title={
                        positionEditing
                            ? `Edit jabatan · ${positionEditing.name}`
                            : 'Tambah jabatan'
                    }
                    onClose={() => {
                        setPositionEditing(null);
                        setPositionModalOpen(false);
                        setPositionError(null);
                    }}
                >
                    <form className="form" onSubmit={onPositionSubmit}>
                        {positionError && <Alert>{positionError}</Alert>}
                        <Field label="Nama jabatan">
                            <input
                                value={positionForm.name}
                                onChange={(event) =>
                                    setPositionForm({
                                        ...positionForm,
                                        name: event.target.value,
                                    })
                                }
                                maxLength={120}
                                required
                            />
                        </Field>
                        <Field label="Urutan hierarki (kecil ke besar)">
                            <input
                                type="number"
                                min={1}
                                max={999}
                                value={positionForm.sort_order}
                                onChange={(event) =>
                                    setPositionForm({
                                        ...positionForm,
                                        sort_order: Number(event.target.value),
                                    })
                                }
                                required
                            />
                        </Field>
                        <label className="check">
                            <input
                                type="checkbox"
                                checked={positionForm.is_active}
                                onChange={(event) =>
                                    setPositionForm({
                                        ...positionForm,
                                        is_active: event.target.checked,
                                    })
                                }
                            />
                            Aktif untuk dipilih sebagai jabatan karyawan
                        </label>
                        <label className="check">
                            <input
                                type="checkbox"
                                checked={positionForm.is_leave_manager}
                                onChange={(event) =>
                                    setPositionForm({
                                        ...positionForm,
                                        is_leave_manager: event.target.checked,
                                    })
                                }
                            />
                            Dapat dipilih sebagai approver cuti
                        </label>
                        <div className="modal-actions">
                            <button
                                type="button"
                                className="btn btn-ghost"
                                onClick={() => {
                                    setPositionEditing(null);
                                    setPositionModalOpen(false);
                                    setPositionError(null);
                                }}
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                className="btn btn-primary"
                                disabled={positionSaving}
                            >
                                {positionSaving
                                    ? 'Menyimpan...'
                                    : 'Simpan jabatan'}
                            </button>
                        </div>
                    </form>
                </Modal>
            )}
        </div>
    );
}
