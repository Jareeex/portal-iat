import { useCallback, useEffect, useState, type FormEvent } from 'react';
import {
    departmentService,
    type DepartmentPayload,
} from '../../services/department.service';
import { errorMessage } from '../../utils/error';
import { Alert, Loader } from '../../components/common/Feedback';
import { Modal } from '../../components/common/Modal';
import { Field } from '../../components/common/Field';
import { DataTable, type Column } from '../../components/tables/DataTable';
import type { Department } from '../../types';

const emptyForm: DepartmentPayload = {
    name: '',
    code: '',
    has_full_access: false,
};

export default function DepartmentsPage() {
    const [items, setItems] = useState<Department[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const [editing, setEditing] = useState<Department | null>(null);
    const [showForm, setShowForm] = useState(false);
    const [form, setForm] = useState<DepartmentPayload>(emptyForm);
    const [saving, setSaving] = useState(false);
    const [formError, setFormError] = useState<string | null>(null);

    const load = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            setItems(await departmentService.list());
        } catch (e) {
            setError(errorMessage(e));
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        void load();
    }, [load]);

    function openCreate() {
        setEditing(null);
        setForm(emptyForm);
        setFormError(null);
        setShowForm(true);
    }

    function openEdit(d: Department) {
        setEditing(d);
        setForm({
            name: d.name,
            code: d.code,
            has_full_access: d.has_full_access,
        });
        setFormError(null);
        setShowForm(true);
    }

    async function onSubmit(e: FormEvent) {
        e.preventDefault();
        setSaving(true);
        setFormError(null);
        const payload = {
            ...form,
            name: form.name.trim(),
            code: form.code.trim().toUpperCase(),
        };
        try {
            if (editing) await departmentService.update(editing.id, payload);
            else await departmentService.create(payload);
            setShowForm(false);
            await load();
        } catch (err) {
            setFormError(errorMessage(err));
        } finally {
            setSaving(false);
        }
    }

    async function onDelete(d: Department) {
        if (
            !window.confirm(
                `Hapus departemen "${d.name}"? Halaman miliknya jadi shared.`,
            )
        )
            return;
        try {
            await departmentService.remove(d.id);
            await load();
        } catch (err) {
            setError(errorMessage(err));
        }
    }

    const columns: Column<Department>[] = [
        {
            header: 'Kode',
            render: (d) => <span className="badge">{d.code}</span>,
        },
        { header: 'Nama', render: (d) => <strong>{d.name}</strong> },
        {
            header: 'Akses',
            render: (d) =>
                d.has_full_access ? (
                    <span className="badge badge-accent">Full access</span>
                ) : (
                    <span className="muted">Halaman departemen</span>
                ),
        },
        { header: 'User', render: (d) => d.users_count ?? 0 },
        { header: 'Halaman', render: (d) => d.pages_count ?? 0 },
        {
            header: '',
            width: '160px',
            render: (d) => (
                <div className="row-actions">
                    <button
                        type="button"
                        className="btn btn-ghost"
                        onClick={() => openEdit(d)}
                    >
                        Edit
                    </button>
                    <button
                        type="button"
                        className="btn btn-danger"
                        onClick={() => void onDelete(d)}
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
                    <h2>Departments</h2>
                    <p className="muted">
                        Departemen dengan full access (mis. BOD) melihat semua
                        halaman tapi bukan master.
                    </p>
                </div>
                <button
                    type="button"
                    className="btn btn-primary"
                    onClick={openCreate}
                >
                    + Tambah departemen
                </button>
            </div>

            {error && <Alert>{error}</Alert>}
            {loading ? (
                <Loader />
            ) : (
                <DataTable
                    columns={columns}
                    rows={items}
                    rowKey={(d) => d.id}
                    empty="Belum ada departemen."
                />
            )}

            {showForm && (
                <Modal
                    title={editing ? 'Edit departemen' : 'Tambah departemen'}
                    onClose={() => setShowForm(false)}
                >
                    <form onSubmit={onSubmit} className="form">
                        {formError && <Alert>{formError}</Alert>}

                        <Field label="Nama">
                            <input
                                value={form.name}
                                onChange={(e) =>
                                    setForm({ ...form, name: e.target.value })
                                }
                                required
                            />
                        </Field>

                        <Field
                            label="Kode"
                            hint="Huruf, angka, dash, underscore. Contoh: HR, IT, BOD."
                        >
                            <input
                                value={form.code}
                                onChange={(e) =>
                                    setForm({ ...form, code: e.target.value })
                                }
                                maxLength={20}
                                pattern="[A-Za-z0-9_\-]+"
                                required
                            />
                        </Field>

                        <label className="check">
                            <input
                                type="checkbox"
                                checked={form.has_full_access}
                                onChange={(e) =>
                                    setForm({
                                        ...form,
                                        has_full_access: e.target.checked,
                                    })
                                }
                            />
                            Full access (bisa lihat semua halaman, seperti BOD)
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
