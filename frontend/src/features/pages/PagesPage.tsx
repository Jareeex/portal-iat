import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { pageService, type PagePayload } from '../../services/page.service';
import { departmentService } from '../../services/department.service';
import { errorMessage } from '../../utils/error';
import { Alert, Loader } from '../../components/common/Feedback';
import { Modal } from '../../components/common/Modal';
import { Field } from '../../components/common/Field';
import { DataTable, type Column } from '../../components/tables/DataTable';
import type { Department, ManagedPage } from '../../types';

interface FormState {
    department_id: number | '';
    key: string;
    name: string;
    description: string;
    route: string;
    icon: string;
    sort_order: number;
    is_active: boolean;
}

const emptyForm: FormState = {
    department_id: '',
    key: '',
    name: '',
    description: '',
    route: '/',
    icon: '',
    sort_order: 0,
    is_active: true,
};

export default function PagesPage() {
    const [items, setItems] = useState<ManagedPage[]>([]);
    const [departments, setDepartments] = useState<Department[]>([]);
    const [deptFilter, setDeptFilter] = useState<number | ''>('');
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const [editing, setEditing] = useState<ManagedPage | null>(null);
    const [showForm, setShowForm] = useState(false);
    const [form, setForm] = useState<FormState>(emptyForm);
    const [saving, setSaving] = useState(false);
    const [formError, setFormError] = useState<string | null>(null);

    const load = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            setItems(await pageService.list(deptFilter));
        } catch (e) {
            setError(errorMessage(e));
        } finally {
            setLoading(false);
        }
    }, [deptFilter]);

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
        setForm({ ...emptyForm, department_id: deptFilter });
        setFormError(null);
        setShowForm(true);
    }

    function openEdit(p: ManagedPage) {
        setEditing(p);
        setForm({
            department_id: p.department_id ?? '',
            key: p.key,
            name: p.name,
            description: p.description ?? '',
            route: p.route,
            icon: p.icon ?? '',
            sort_order: p.sort_order,
            is_active: p.is_active,
        });
        setFormError(null);
        setShowForm(true);
    }

    async function onSubmit(e: FormEvent) {
        e.preventDefault();
        setSaving(true);
        setFormError(null);

        const payload: PagePayload = {
            department_id:
                form.department_id === '' ? null : form.department_id,
            key: form.key.trim(),
            name: form.name.trim(),
            description: form.description.trim() || null,
            route: form.route.trim(),
            icon: form.icon.trim() || null,
            sort_order: form.sort_order,
            is_active: form.is_active,
        };

        try {
            if (editing) await pageService.update(editing.id, payload);
            else await pageService.create(payload);
            setShowForm(false);
            await load();
        } catch (err) {
            setFormError(errorMessage(err));
        } finally {
            setSaving(false);
        }
    }

    async function onDelete(p: ManagedPage) {
        if (
            !window.confirm(
                `Hapus halaman "${p.name}"? Akses tambahan ke halaman ini ikut terhapus.`,
            )
        )
            return;
        try {
            await pageService.remove(p.id);
            await load();
        } catch (err) {
            setError(errorMessage(err));
        }
    }

    const columns: Column<ManagedPage>[] = [
        { header: 'Urut', width: '70px', render: (p) => p.sort_order },
        {
            header: 'Halaman',
            render: (p) => (
                <div>
                    <strong>{p.name}</strong>
                    <div className="muted">
                        <code>{p.key}</code> · {p.route}
                    </div>
                </div>
            ),
        },
        {
            header: 'Departemen',
            render: (p) =>
                p.department ? (
                    <span className="badge">{p.department.code}</span>
                ) : (
                    <span className="badge badge-accent">Shared</span>
                ),
        },
        {
            header: 'Status',
            render: (p) => (
                <span
                    className={
                        p.is_active ? 'badge badge-ok' : 'badge badge-off'
                    }
                >
                    {p.is_active ? 'Aktif' : 'Nonaktif'}
                </span>
            ),
        },
        {
            header: '',
            width: '160px',
            render: (p) => (
                <div className="row-actions">
                    <button
                        type="button"
                        className="btn btn-ghost"
                        onClick={() => openEdit(p)}
                    >
                        Edit
                    </button>
                    <button
                        type="button"
                        className="btn btn-danger"
                        onClick={() => void onDelete(p)}
                    >
                        Hapus
                    </button>
                </div>
            ),
        },
    ];

    return (
        <div>
            <div className="page-head page-head-row">
                <div>
                    <h2>Pages</h2>
                    <p className="muted">
                        Halaman/fitur portal per departemen. Tanpa departemen =
                        shared untuk semua user.
                    </p>
                </div>
                <button
                    type="button"
                    className="btn btn-primary"
                    onClick={openCreate}
                >
                    + Tambah halaman
                </button>
            </div>

            <div className="toolbar">
                <select
                    value={deptFilter}
                    onChange={(e) =>
                        setDeptFilter(
                            e.target.value === '' ? '' : Number(e.target.value),
                        )
                    }
                >
                    <option value="">Semua departemen</option>
                    {departments.map((d) => (
                        <option key={d.id} value={d.id}>
                            {d.name}
                        </option>
                    ))}
                </select>
            </div>

            {error && <Alert>{error}</Alert>}
            {loading ? (
                <Loader />
            ) : (
                <DataTable
                    columns={columns}
                    rows={items}
                    rowKey={(p) => p.id}
                    empty="Belum ada halaman."
                />
            )}

            {showForm && (
                <Modal
                    title={editing ? 'Edit halaman' : 'Tambah halaman'}
                    onClose={() => setShowForm(false)}
                >
                    <form onSubmit={onSubmit} className="form">
                        {formError && <Alert>{formError}</Alert>}

                        <Field
                            label="Departemen"
                            hint="Kosong = shared, semua user login bisa buka."
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
                                <option value="">
                                    - Shared (semua user) -
                                </option>
                                {departments.map((d) => (
                                    <option key={d.id} value={d.id}>
                                        {d.name}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field label="Nama (label sidebar)">
                            <input
                                value={form.name}
                                onChange={(e) =>
                                    setForm({ ...form, name: e.target.value })
                                }
                                required
                            />
                        </Field>

                        <Field
                            label="Key"
                            hint="Unik, dipakai di backend (page.access:key) dan registry frontend. Contoh: hr-employees."
                        >
                            <input
                                value={form.key}
                                onChange={(e) =>
                                    setForm({ ...form, key: e.target.value })
                                }
                                pattern="[A-Za-z0-9_\-]+"
                                required
                            />
                        </Field>

                        <Field
                            label="Route frontend"
                            hint="Contoh: /hr/employees"
                        >
                            <input
                                value={form.route}
                                onChange={(e) =>
                                    setForm({ ...form, route: e.target.value })
                                }
                                required
                            />
                        </Field>

                        <Field label="Deskripsi">
                            <input
                                value={form.description}
                                onChange={(e) =>
                                    setForm({
                                        ...form,
                                        description: e.target.value,
                                    })
                                }
                            />
                        </Field>

                        <div className="form-row">
                            <Field label="Icon (opsional)">
                                <input
                                    value={form.icon}
                                    onChange={(e) =>
                                        setForm({
                                            ...form,
                                            icon: e.target.value,
                                        })
                                    }
                                />
                            </Field>
                            <Field label="Urutan">
                                <input
                                    type="number"
                                    min={0}
                                    value={form.sort_order}
                                    onChange={(e) =>
                                        setForm({
                                            ...form,
                                            sort_order: Number(e.target.value),
                                        })
                                    }
                                />
                            </Field>
                        </div>

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
                            />
                            Halaman aktif
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
