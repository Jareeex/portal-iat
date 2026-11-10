import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { Alert, Loader } from '../../components/common/Feedback';
import { Field } from '../../components/common/Field';
import { Modal } from '../../components/common/Modal';
import { DataTable, type Column } from '../../components/tables/DataTable';
import { Pagination } from '../../components/tables/Pagination';
import { usePermission } from '../../hooks/usePermission';
import {
    itAssetService,
    type ItAsset,
    type ItAssetCategory,
    type ItAssetFilters,
    type ItAssetPayload,
    type ItAssetSummary,
} from '../../services/it-asset.service';
import { errorMessage } from '../../utils/error';

const statuses = ['Aktif', 'Tidak Aktif', 'Dalam Perbaikan', 'Dihapus'];
const conditions = ['Baik', 'Kurang Baik', 'Rusak'];

const emptyForm: ItAssetPayload = {
    asset_category_id: 0,
    brand: '',
    serial_number: null,
    user_name: null,
    division: null,
    condition: null,
    notes: null,
    status: 'Aktif',
};

function downloadFile(blob: Blob, fileName: string): void {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = fileName;
    link.click();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}

export default function AssetsPage() {
    const { canEdit, isMaster } = usePermission();
    const canManage = isMaster || canEdit('it-assets');
    const [assets, setAssets] = useState<ItAsset[]>([]);
    const [categories, setCategories] = useState<ItAssetCategory[]>([]);
    const [summary, setSummary] = useState<ItAssetSummary | null>(null);
    const [page, setPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [total, setTotal] = useState(0);
    const [searchInput, setSearchInput] = useState('');
    const [filters, setFilters] = useState<Omit<ItAssetFilters, 'page'>>({});
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [editing, setEditing] = useState<ItAsset | null>(null);
    const [assetModalOpen, setAssetModalOpen] = useState(false);
    const [form, setForm] = useState<ItAssetPayload>(emptyForm);
    const [formError, setFormError] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);
    const [categoryEditing, setCategoryEditing] =
        useState<ItAssetCategory | null>(null);
    const [categoryModalOpen, setCategoryModalOpen] = useState(false);
    const [categoryForm, setCategoryForm] = useState({ code: '', name: '' });
    const [categoryError, setCategoryError] = useState<string | null>(null);
    const [categorySaving, setCategorySaving] = useState(false);

    const loadAssets = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const result = await itAssetService.list({ ...filters, page });
            setAssets(result.data);
            setLastPage(result.last_page);
            setTotal(result.total);
        } catch (loadError) {
            setError(errorMessage(loadError));
        } finally {
            setLoading(false);
        }
    }, [filters, page]);

    const loadMetadata = useCallback(async () => {
        try {
            const [categoryList, activeSummary] = await Promise.all([
                itAssetService.categories(),
                itAssetService.summary(),
            ]);
            setCategories(categoryList);
            setSummary(activeSummary);
        } catch (loadError) {
            setError(errorMessage(loadError));
        }
    }, []);

    useEffect(() => {
        void loadAssets();
    }, [loadAssets]);

    useEffect(() => {
        void loadMetadata();
    }, [loadMetadata]);

    function openCreate(): void {
        setEditing(null);
        setForm({
            ...emptyForm,
            asset_category_id: categories[0]?.id ?? 0,
        });
        setFormError(null);
        setAssetModalOpen(true);
    }

    function openEdit(asset: ItAsset): void {
        setEditing(asset);
        setForm({
            asset_category_id: asset.category.id,
            brand: asset.brand,
            serial_number: asset.serial_number,
            user_name: asset.holder,
            division: asset.division,
            condition: asset.condition,
            notes: asset.notes,
            status: asset.status,
        });
        setFormError(null);
        setAssetModalOpen(true);
    }

    async function submitAsset(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        setSaving(true);
        setFormError(null);

        const payload: ItAssetPayload = {
            ...form,
            serial_number: form.serial_number?.trim() || null,
            user_name: form.user_name?.trim() || null,
            division: form.division?.trim() || null,
            condition: form.condition || null,
            notes: form.notes?.trim() || null,
        };

        try {
            if (editing) {
                await itAssetService.update(editing.id, payload);
            } else {
                await itAssetService.create(payload);
            }
            setAssetModalOpen(false);
            await Promise.all([loadAssets(), loadMetadata()]);
        } catch (saveError) {
            setFormError(errorMessage(saveError));
        } finally {
            setSaving(false);
        }
    }

    async function removeAsset(asset: ItAsset): Promise<void> {
        if (!window.confirm(`Hapus aset "${asset.brand}"?`)) return;

        try {
            await itAssetService.remove(asset.id);
            await Promise.all([loadAssets(), loadMetadata()]);
        } catch (deleteError) {
            setError(errorMessage(deleteError));
        }
    }

    function openCategory(category?: ItAssetCategory): void {
        setCategoryEditing(category ?? null);
        setCategoryForm(
            category
                ? { code: String(category.code), name: category.name }
                : { code: '', name: '' },
        );
        setCategoryError(null);
        setCategoryModalOpen(true);
    }

    async function submitCategory(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        setCategorySaving(true);
        setCategoryError(null);
        const payload = {
            code: Number(categoryForm.code),
            name: categoryForm.name.trim(),
        };

        try {
            if (categoryEditing) {
                await itAssetService.updateCategory(categoryEditing.id, payload);
            } else {
                await itAssetService.createCategory(payload);
            }
            setCategoryModalOpen(false);
            await loadMetadata();
        } catch (saveError) {
            setCategoryError(errorMessage(saveError));
        } finally {
            setCategorySaving(false);
        }
    }

    async function removeCategory(category: ItAssetCategory): Promise<void> {
        if (!window.confirm(`Hapus kategori "${category.name}"?`)) return;

        try {
            await itAssetService.removeCategory(category.id);
            await loadMetadata();
        } catch (deleteError) {
            setError(errorMessage(deleteError));
        }
    }

    async function exportPdf(): Promise<void> {
        try {
            downloadFile(await itAssetService.exportPdf(filters), 'it-assets.pdf');
        } catch (exportError) {
            setError(errorMessage(exportError));
        }
    }

    const columns: Column<ItAsset>[] = [
        {
            header: 'Kategori',
            render: (asset) => (
                <div>
                    <strong>{asset.category.name}</strong>
                    <div className="muted">Kode {asset.category.code}</div>
                </div>
            ),
        },
        { header: 'Merk barang', render: (asset) => asset.brand },
        {
            header: 'Serial number',
            render: (asset) => asset.serial_number || '-',
        },
        { header: 'Pemakai', render: (asset) => asset.holder || '-' },
        { header: 'Divisi', render: (asset) => asset.division || '-' },
        { header: 'Kondisi', render: (asset) => asset.condition || '-' },
        {
            header: 'Status',
            render: (asset) => (
                <span
                    className={
                        asset.status === 'Aktif'
                            ? 'badge badge-ok'
                            : 'badge badge-off'
                    }
                >
                    {asset.status}
                </span>
            ),
        },
        ...(canManage
            ? [
                  {
                      header: 'Aksi',
                      width: '150px',
                      render: (asset: ItAsset) => (
                          <div className="row-actions">
                              <button
                                  type="button"
                                  className="btn btn-ghost"
                                  onClick={() => openEdit(asset)}
                              >
                                  Edit
                              </button>
                              <button
                                  type="button"
                                  className="btn btn-danger"
                                  onClick={() => void removeAsset(asset)}
                              >
                                  Hapus
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
                    <h2>IT Assets</h2>
                    <p className="muted">Inventaris aset IT. Total: {total}</p>
                </div>
                {canManage && (
                    <div className="row-actions">
                        <button
                            type="button"
                            className="btn btn-primary"
                            onClick={openCreate}
                            disabled={categories.length === 0}
                        >
                            + Tambah aset
                        </button>
                        <button
                            type="button"
                            className="btn"
                            onClick={() => openCategory()}
                        >
                            Kategori
                        </button>
                    </div>
                )}
            </div>

            {summary && (
                <section className="panel asset-summary">
                    <strong>Total aset aktif: {summary.total}</strong>
                    <span className="muted">
                        {summary.rows
                            .map((row) => `${row.name}: ${row.total}`)
                            .join(' · ') || 'Belum ada aset aktif'}
                    </span>
                </section>
            )}

            <form
                className="toolbar"
                onSubmit={(event) => {
                    event.preventDefault();
                    setPage(1);
                    setFilters({ ...filters, q: searchInput.trim() || undefined });
                }}
            >
                <input
                    type="search"
                    placeholder="Cari merk, serial, pemakai, divisi"
                    value={searchInput}
                    onChange={(event) => setSearchInput(event.target.value)}
                />
                <select
                    value={filters.category ?? ''}
                    onChange={(event) => {
                        setPage(1);
                        setFilters({
                            ...filters,
                            category:
                                event.target.value === ''
                                    ? ''
                                    : Number(event.target.value),
                        });
                    }}
                >
                    <option value="">Semua kategori</option>
                    {categories.map((category) => (
                        <option key={category.id} value={category.id}>
                            {category.code} - {category.name}
                        </option>
                    ))}
                </select>
                <select
                    value={filters.status ?? ''}
                    onChange={(event) => {
                        setPage(1);
                        setFilters({ ...filters, status: event.target.value });
                    }}
                >
                    <option value="">Semua status</option>
                    {statuses.map((status) => (
                        <option key={status} value={status}>
                            {status}
                        </option>
                    ))}
                </select>
                <select
                    value={filters.condition ?? ''}
                    onChange={(event) => {
                        setPage(1);
                        setFilters({ ...filters, condition: event.target.value });
                    }}
                >
                    <option value="">Semua kondisi</option>
                    {conditions.map((condition) => (
                        <option key={condition} value={condition}>
                            {condition}
                        </option>
                    ))}
                </select>
                <button type="submit" className="btn">
                    Cari
                </button>
            </form>

            {canManage && (
                <div className="asset-tools">
                    <button
                        type="button"
                        className="btn btn-primary"
                        onClick={() => void exportPdf()}
                    >
                        Unduh PDF
                    </button>
                </div>
            )}

            {error && <Alert>{error}</Alert>}
            {loading ? (
                <Loader />
            ) : (
                <>
                    <DataTable
                        columns={columns}
                        rows={assets}
                        rowKey={(asset) => asset.id}
                        empty="Belum ada data aset."
                    />
                    <Pagination
                        page={page}
                        lastPage={lastPage}
                        total={total}
                        onChange={setPage}
                    />
                </>
            )}

            {assetModalOpen && (
                <Modal
                    title={editing ? 'Edit aset' : 'Tambah aset'}
                    onClose={() => setAssetModalOpen(false)}
                >
                    <form className="form" onSubmit={submitAsset}>
                        {formError && <Alert>{formError}</Alert>}
                        <Field label="Kategori">
                            <select
                                value={form.asset_category_id || ''}
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        asset_category_id: Number(event.target.value),
                                    })
                                }
                                required
                            >
                                <option value="">Pilih kategori</option>
                                {categories.map((category) => (
                                    <option key={category.id} value={category.id}>
                                        {category.code} - {category.name}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Merk barang">
                            <input
                                value={form.brand}
                                onChange={(event) =>
                                    setForm({ ...form, brand: event.target.value })
                                }
                                maxLength={100}
                                required
                            />
                        </Field>
                        <Field label="Serial number">
                            <input
                                value={form.serial_number ?? ''}
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        serial_number: event.target.value,
                                    })
                                }
                                maxLength={100}
                            />
                        </Field>
                        <Field label="Nama pemakai">
                            <input
                                value={form.user_name ?? ''}
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        user_name: event.target.value,
                                    })
                                }
                                maxLength={150}
                            />
                        </Field>
                        <Field label="Divisi">
                            <input
                                value={form.division ?? ''}
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        division: event.target.value,
                                    })
                                }
                                maxLength={120}
                            />
                        </Field>
                        <Field label="Kondisi">
                            <select
                                value={form.condition ?? ''}
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        condition: event.target.value || null,
                                    })
                                }
                            >
                                <option value="">Belum ditentukan</option>
                                {conditions.map((condition) => (
                                    <option key={condition} value={condition}>
                                        {condition}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Status">
                            <select
                                value={form.status}
                                onChange={(event) =>
                                    setForm({ ...form, status: event.target.value })
                                }
                            >
                                {statuses.map((status) => (
                                    <option key={status} value={status}>
                                        {status}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Keterangan">
                            <textarea
                                value={form.notes ?? ''}
                                onChange={(event) =>
                                    setForm({ ...form, notes: event.target.value })
                                }
                                maxLength={2000}
                            />
                        </Field>
                        <div className="modal-actions">
                            <button
                                type="button"
                                className="btn btn-ghost"
                                onClick={() => setAssetModalOpen(false)}
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                className="btn btn-primary"
                                disabled={saving}
                            >
                                {saving ? 'Menyimpan...' : 'Simpan aset'}
                            </button>
                        </div>
                    </form>
                </Modal>
            )}

            {categoryModalOpen && (
                <Modal
                    title="Kategori aset"
                    onClose={() => setCategoryModalOpen(false)}
                >
                    <div className="form">
                        {categoryError && <Alert>{categoryError}</Alert>}
                        <DataTable
                            columns={[
                                { header: 'Kode', render: (category) => category.code },
                                { header: 'Nama', render: (category) => category.name },
                                {
                                    header: 'Aset',
                                    render: (category) => category.assets_count ?? 0,
                                },
                                {
                                    header: 'Aksi',
                                    width: '150px',
                                    render: (category) => (
                                        <div className="row-actions">
                                            <button
                                                type="button"
                                                className="btn btn-ghost"
                                                onClick={() => openCategory(category)}
                                            >
                                                Edit
                                            </button>
                                            <button
                                                type="button"
                                                className="btn btn-danger"
                                                onClick={() =>
                                                    void removeCategory(category)
                                                }
                                            >
                                                Hapus
                                            </button>
                                        </div>
                                    ),
                                },
                            ]}
                            rows={categories}
                            rowKey={(category) => category.id}
                            empty="Belum ada kategori."
                        />
                        <form className="form" onSubmit={submitCategory}>
                            <div className="form-row">
                                <Field label="Kode">
                                    <input
                                        type="number"
                                        min={1}
                                        max={65000}
                                        value={categoryForm.code}
                                        onChange={(event) =>
                                            setCategoryForm({
                                                ...categoryForm,
                                                code: event.target.value,
                                            })
                                        }
                                        required
                                    />
                                </Field>
                                <Field label="Nama kategori">
                                    <input
                                        value={categoryForm.name}
                                        onChange={(event) =>
                                            setCategoryForm({
                                                ...categoryForm,
                                                name: event.target.value,
                                            })
                                        }
                                        maxLength={100}
                                        required
                                    />
                                </Field>
                            </div>
                            <div className="modal-actions">
                                {categoryEditing && (
                                    <button
                                        type="button"
                                        className="btn btn-ghost"
                                        onClick={() => openCategory()}
                                    >
                                        Kategori baru
                                    </button>
                                )}
                                <button
                                    type="submit"
                                    className="btn btn-primary"
                                    disabled={categorySaving}
                                >
                                    {categorySaving ? 'Menyimpan...' : 'Simpan kategori'}
                                </button>
                            </div>
                        </form>
                    </div>
                </Modal>
            )}
        </div>
    );
}
