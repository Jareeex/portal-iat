import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { userService } from '../../services/user.service';
import { accessService } from '../../services/access.service';
import { errorMessage } from '../../utils/error';
import { Alert, EmptyState, Loader } from '../../components/common/Feedback';
import type { AccessResponse, ManagedUser } from '../../types';

type AccessPermission = 'view' | 'edit' | '';

export default function AccessPage() {
    const [params, setParams] = useSearchParams();
    const userId = Number(params.get('user')) || null;

    const [users, setUsers] = useState<ManagedUser[]>([]);
    const [access, setAccess] = useState<AccessResponse | null>(null);
    const [permissions, setPermissions] = useState<
        Record<number, AccessPermission>
    >({});

    const [loadingUsers, setLoadingUsers] = useState(true);
    const [loadingAccess, setLoadingAccess] = useState(false);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [saved, setSaved] = useState(false);

    useEffect(() => {
        userService
            .list({ per_page: 100 })
            .then((res) => setUsers(res.data))
            .catch((e) => setError(errorMessage(e)))
            .finally(() => setLoadingUsers(false));
    }, []);

    useEffect(() => {
        setSaved(false);
        setAccess(null);
        if (!userId) return;

        setLoadingAccess(true);
        setError(null);
        accessService
            .get(userId)
            .then((res) => {
                setAccess(res);
                setPermissions(
                    Object.fromEntries(
                        res.pages.map((p) => [p.id, p.permission ?? '']),
                    ),
                );
            })
            .catch((e) => setError(errorMessage(e)))
            .finally(() => setLoadingAccess(false));
    }, [userId]);

    async function onSave() {
        if (!userId || !access) return;
        setSaving(true);
        setError(null);
        setSaved(false);

        const ids = access.pages
            .filter((page) => permissions[page.id])
            .map((page) => page.id);
        const editIds = access.pages
            .filter((page) => permissions[page.id] === 'edit')
            .map((page) => page.id);

        try {
            await accessService.update(userId, ids, editIds);
            setSaved(true);
        } catch (e) {
            setError(errorMessage(e));
        } finally {
            setSaving(false);
        }
    }

    const selectedUser = users.find((u) => u.id === userId);

    return (
        <div>
            <div className="page-head">
                <h2>Access</h2>
                <p className="muted">
                    Master dapat mengatur user sebagai view only atau editable
                    untuk setiap halaman.
                </p>
            </div>

            <div className="toolbar">
                <select
                    value={userId ?? ''}
                    disabled={loadingUsers}
                    onChange={(e) =>
                        setParams(
                            e.target.value ? { user: e.target.value } : {},
                        )
                    }
                >
                    <option value="">Pilih user...</option>
                    {users.map((u) => (
                        <option key={u.id} value={u.id}>
                            {u.name} ({u.department?.code ?? 'tanpa departemen'}
                            )
                        </option>
                    ))}
                </select>
            </div>

            {error && <Alert>{error}</Alert>}
            {saved && (
                <Alert kind="success">
                    Akses {selectedUser?.name ?? 'user'} berhasil disimpan.
                </Alert>
            )}

            {!userId && (
                <EmptyState>Pilih user untuk mengatur aksesnya.</EmptyState>
            )}
            {loadingAccess && <Loader />}

            {access && !loadingAccess && (
                <div className="panel">
                    {access.full_access && (
                        <Alert kind="info">
                            User ini mendapat akses halaman secara otomatis dari
                            role atau departemennya.
                            {access.is_master &&
                                ' Akun master selalu memiliki akses editable.'}
                        </Alert>
                    )}

                    <ul className="access-list">
                        {access.pages.map((p) => (
                            <li
                                key={p.id}
                                className={
                                    p.by_department
                                        ? 'access-item locked'
                                        : 'access-item'
                                }
                            >
                                <span>
                                    <strong>{p.name}</strong>{' '}
                                    <code>{p.key}</code>
                                </span>
                                <label className="access-level">
                                    <span className="sr-only">
                                        Hak akses {p.name}
                                    </span>
                                    <select
                                        value={permissions[p.id] ?? ''}
                                        disabled={access.is_master}
                                        onChange={(event) =>
                                            setPermissions({
                                                ...permissions,
                                                [p.id]: event.target
                                                    .value as AccessPermission,
                                            })
                                        }
                                    >
                                        {!p.by_department && (
                                            <option value="">
                                                Tidak ada akses
                                            </option>
                                        )}
                                        <option value="view">View only</option>
                                        <option value="edit">Editable</option>
                                    </select>
                                </label>
                                <span className="access-meta">
                                    {!p.is_active && (
                                        <span className="badge badge-off">
                                            Nonaktif
                                        </span>
                                    )}
                                    {p.department ? (
                                        <span className="badge">
                                            {p.department.code}
                                        </span>
                                    ) : (
                                        <span className="badge badge-accent">
                                            Shared
                                        </span>
                                    )}
                                    {p.by_department && (
                                        <span className="muted">
                                            akses otomatis
                                        </span>
                                    )}
                                </span>
                            </li>
                        ))}
                    </ul>

                    <div className="modal-actions">
                        <button
                            type="button"
                            className="btn btn-primary"
                            onClick={() => void onSave()}
                            disabled={saving || access.is_master}
                        >
                            {saving ? 'Menyimpan...' : 'Simpan akses'}
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
