import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { useNavigate } from 'react-router-dom';
import { userService, type UserPayload } from '../../services/user.service';
import { departmentService } from '../../services/department.service';
import { errorMessage } from '../../utils/error';
import { useAuth } from '../../hooks/useAuth';
import { Alert, Loader } from '../../components/common/Feedback';
import { employeeService } from '../../services/employee.service';
import { Modal } from '../../components/common/Modal';
import { Field } from '../../components/common/Field';
import { DataTable, type Column } from '../../components/tables/DataTable';
import { Pagination } from '../../components/tables/Pagination';
import type {
    Department,
    EmployeeLinkOption,
    ManagedUser,
    Role,
} from '../../types';

interface FormState {
    name: string;
    email: string;
    password: string;
    role: Role;
    is_active: boolean;
    department_id: number | '';
    employee_id: number | '';
}

const emptyForm: FormState = {
    name: '',
    email: '',
    password: '',
    role: 'user',
    is_active: true,
    department_id: '',
    employee_id: '',
};

export default function UsersPage() {
    const { user: me } = useAuth();
    const navigate = useNavigate();

    const [users, setUsers] = useState<ManagedUser[]>([]);
    const [departments, setDepartments] = useState<Department[]>([]);
    const [unlinkedEmployees, setUnlinkedEmployees] = useState<
        EmployeeLinkOption[]
    >([]);
    const [employeeOptionsError, setEmployeeOptionsError] = useState<
        string | null
    >(null);
    const [page, setPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [total, setTotal] = useState(0);
    const [searchInput, setSearchInput] = useState('');
    const [search, setSearch] = useState('');
    const [deptFilter, setDeptFilter] = useState<number | ''>('');

    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const [editing, setEditing] = useState<ManagedUser | null>(null);
    const [showForm, setShowForm] = useState(false);
    const [form, setForm] = useState<FormState>(emptyForm);
    const [saving, setSaving] = useState(false);
    const [formError, setFormError] = useState<string | null>(null);
    const [updatingOnLeaveUserId, setUpdatingOnLeaveUserId] = useState<
        number | null
    >(null);

    const load = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const res = await userService.list({
                page,
                search: search || undefined,
                department_id: deptFilter,
            });
            setUsers(res.data);
            setLastPage(res.last_page);
            setTotal(res.total);
        } catch (e) {
            setError(errorMessage(e));
        } finally {
            setLoading(false);
        }
    }, [page, search, deptFilter]);

    useEffect(() => {
        void load();
    }, [load]);

    useEffect(() => {
        departmentService
            .list()
            .then(setDepartments)
            .catch(() => setDepartments([]));
    }, []);

    function openCreate() {
        setEditing(null);
        setForm(emptyForm);
        setFormError(null);
        setUnlinkedEmployees([]);
        void loadUnlinkedEmployees();
        setShowForm(true);
    }

    async function loadUnlinkedEmployees(): Promise<void> {
        setEmployeeOptionsError(null);
        try {
            setUnlinkedEmployees(await employeeService.unlinked());
        } catch (cause) {
            setEmployeeOptionsError(errorMessage(cause));
        }
    }

    function openEdit(u: ManagedUser) {
        setEditing(u);
        setForm({
            name: u.name,
            email: u.email,
            password: '',
            role: u.role,
            is_active: u.is_active,
            department_id: u.department_id ?? '',
            employee_id: '',
        });
        setFormError(null);
        setEmployeeOptionsError(null);
        setShowForm(true);
    }

    async function onSubmit(e: FormEvent) {
        e.preventDefault();
        setSaving(true);
        setFormError(null);

        const payload: UserPayload = {
            name: form.name.trim(),
            email: form.email.trim(),
            role: form.role,
            is_active: form.is_active,
            department_id:
                form.department_id === '' ? null : form.department_id,
        };
        if (!editing && form.employee_id !== '') {
            payload.employee_id = form.employee_id;
        }
        if (form.password) payload.password = form.password;

        try {
            if (editing) await userService.update(editing.id, payload);
            else await userService.create(payload);
            setShowForm(false);
            await load();
        } catch (err) {
            setFormError(errorMessage(err));
        } finally {
            setSaving(false);
        }
    }

    async function onDelete(u: ManagedUser) {
        if (!window.confirm(`Hapus user "${u.name}"?`)) return;
        try {
            await userService.remove(u.id);
            await load();
        } catch (err) {
            setError(errorMessage(err));
        }
    }

    async function updateOnLeave(u: ManagedUser, enabled: boolean) {
        setUpdatingOnLeaveUserId(u.id);
        setError(null);
        try {
            const updatedUser = await userService.updateOnLeave(u.id, enabled);
            setUsers((currentUsers) =>
                currentUsers.map((currentUser) =>
                    currentUser.id === updatedUser.id
                        ? {
                              ...currentUser,
                              on_leave_enabled:
                                  updatedUser.on_leave_enabled,
                          }
                        : currentUser,
                ),
            );
        } catch (updateError) {
            setError(errorMessage(updateError));
        } finally {
            setUpdatingOnLeaveUserId(null);
        }
    }

    const isSelf = editing !== null && editing.id === me?.id;

    const columns: Column<ManagedUser>[] = [
        {
            header: 'Nama',
            render: (u) => (
                <div>
                    <strong>{u.name}</strong>
                    <div className="muted">{u.email}</div>
                </div>
            ),
        },
        {
            header: 'Departemen',
            render: (u) =>
                u.department ? (
                    <span className="badge">
                        {u.department.code}
                        {u.department.has_full_access ? ' · full' : ''}
                    </span>
                ) : (
                    <span className="muted">-</span>
                ),
        },
        {
            header: 'Role',
            render: (u) => (
                <span
                    className={
                        u.role === 'master' ? 'badge badge-accent' : 'badge'
                    }
                >
                    {u.role}
                </span>
            ),
        },
        {
            header: 'Status',
            render: (u) => (
                <span
                    className={
                        u.is_active ? 'badge badge-ok' : 'badge badge-off'
                    }
                >
                    {u.is_active ? 'Aktif' : 'Nonaktif'}
                </span>
            ),
        },
        {
            header: 'On Leave',
            render: (u) => (
                <label className="check">
                    <input
                        type="checkbox"
                        aria-label={`Aktifkan On Leave untuk ${u.name}`}
                        checked={u.on_leave_enabled}
                        disabled={updatingOnLeaveUserId === u.id}
                        onChange={(event) =>
                            void updateOnLeave(u, event.target.checked)
                        }
                    />
                    On Leave
                </label>
            ),
        },
        { header: 'Akses tambahan', render: (u) => u.pages_count ?? 0 },
        {
            header: '',
            width: '230px',
            render: (u) => (
                <div className="row-actions">
                    <button
                        type="button"
                        className="btn btn-ghost"
                        onClick={() => navigate(`/master/access?user=${u.id}`)}
                    >
                        Akses
                    </button>
                    <button
                        type="button"
                        className="btn btn-ghost"
                        onClick={() => openEdit(u)}
                    >
                        Edit
                    </button>
                    {u.id !== me?.id && (
                        <button
                            type="button"
                            className="btn btn-danger"
                            onClick={() => void onDelete(u)}
                        >
                            Hapus
                        </button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <div>
            <div className="page-head page-head-row">
                <div>
                    <h2>Users</h2>
                    <p className="muted">
                        Kelola akun dan departemen. Halaman departemen terbuka
                        otomatis. On Leave mengizinkan absensi luar radius dan
                        statusnya dicatat otomatis dari lokasi.
                    </p>
                </div>
                <button
                    type="button"
                    className="btn btn-primary"
                    onClick={openCreate}
                >
                    + Tambah user
                </button>
            </div>

            <form
                className="toolbar"
                onSubmit={(e) => {
                    e.preventDefault();
                    setPage(1);
                    setSearch(searchInput.trim());
                }}
            >
                <input
                    type="search"
                    placeholder="Cari nama atau email"
                    value={searchInput}
                    onChange={(e) => setSearchInput(e.target.value)}
                />
                <select
                    value={deptFilter}
                    onChange={(e) => {
                        setPage(1);
                        setDeptFilter(
                            e.target.value === '' ? '' : Number(e.target.value),
                        );
                    }}
                >
                    <option value="">Semua departemen</option>
                    {departments.map((d) => (
                        <option key={d.id} value={d.id}>
                            {d.name}
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
                        rows={users}
                        rowKey={(u) => u.id}
                        empty="User tidak ditemukan."
                    />
                    <Pagination
                        page={page}
                        lastPage={lastPage}
                        total={total}
                        onChange={setPage}
                    />
                </>
            )}

            {showForm && (
                <Modal
                    title={editing ? 'Edit user' : 'Tambah user'}
                    onClose={() => setShowForm(false)}
                >
                    <form onSubmit={onSubmit} className="form">
                        {formError && <Alert>{formError}</Alert>}
                        {employeeOptionsError && (
                            <Alert>{employeeOptionsError}</Alert>
                        )}

                        <Field label="Nama">
                            <input
                                value={form.name}
                                onChange={(e) =>
                                    setForm({ ...form, name: e.target.value })
                                }
                                required
                            />
                        </Field>

                        <Field label="Email">
                            <input
                                type="email"
                                value={form.email}
                                onChange={(e) =>
                                    setForm({ ...form, email: e.target.value })
                                }
                                required
                            />
                        </Field>

                        {!editing && (
                            <Field
                                label="Employee yang dihubungkan"
                                hint="Opsional. Pilih profil yang sudah dibuat HR, jika akun ini milik employee tersebut."
                            >
                                <select
                                    value={form.employee_id}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            employee_id:
                                                event.target.value === ''
                                                    ? ''
                                                    : Number(
                                                          event.target.value,
                                                      ),
                                        })
                                    }
                                >
                                    <option value="">
                                        - Tidak dihubungkan -
                                    </option>
                                    {unlinkedEmployees.map((employee) => (
                                        <option
                                            key={employee.id}
                                            value={employee.id}
                                        >
                                            {employee.name}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                        )}

                        <Field
                            label="Password"
                            hint={
                                editing
                                    ? 'Kosongkan kalau tidak diganti.'
                                    : 'Minimal 8 karakter.'
                            }
                        >
                            <input
                                type="password"
                                autoComplete="new-password"
                                value={form.password}
                                onChange={(e) =>
                                    setForm({
                                        ...form,
                                        password: e.target.value,
                                    })
                                }
                                minLength={8}
                                required={!editing}
                            />
                        </Field>

                        <Field
                            label="Departemen"
                            hint="Kosongkan untuk user tanpa departemen (hanya halaman shared)."
                        >
                            <select
                                value={form.department_id}
                                onChange={(e) =>
                                    setForm({
                                        ...form,
                                        department_id:
                                            e.target.value === ''
                                                ? ''
                                                : Number(e.target.value),
                                    })
                                }
                            >
                                <option value="">- Tanpa departemen -</option>
                                {departments.map((d) => (
                                    <option key={d.id} value={d.id}>
                                        {d.name}
                                        {d.has_full_access
                                            ? ' (full access)'
                                            : ''}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field
                            label="Role"
                            hint={
                                isSelf
                                    ? 'Role dan status akun sendiri tidak bisa diubah.'
                                    : 'Master bisa kelola semua. BOD cukup pilih departemen BOD, bukan master.'
                            }
                        >
                            <select
                                value={form.role}
                                onChange={(e) =>
                                    setForm({
                                        ...form,
                                        role: e.target.value as Role,
                                    })
                                }
                                disabled={isSelf}
                            >
                                <option value="user">user</option>
                                <option value="master">master</option>
                            </select>
                        </Field>

                        <label className="check">
                            <input
                                type="checkbox"
                                checked={form.is_active}
                                onChange={(e) =>
                                    setForm({
                                        ...form,
                                        is_active: e.target.checked,
                                    })
                                }
                                disabled={isSelf}
                            />
                            Akun aktif
                        </label>

                        <div className="modal-actions">
                            <button
                                type="button"
                                className="btn btn-ghost"
                                onClick={() => setShowForm(false)}
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                className="btn btn-primary"
                                disabled={saving}
                            >
                                {saving ? 'Menyimpan...' : 'Simpan'}
                            </button>
                        </div>
                    </form>
                </Modal>
            )}
        </div>
    );
}
