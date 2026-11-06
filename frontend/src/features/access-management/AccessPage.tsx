import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { userService } from '../../services/user.service';
import { accessService } from '../../services/access.service';
import { errorMessage } from '../../utils/error';
import { Alert, EmptyState, Loader } from '../../components/common/Feedback';
import type { AccessResponse, ManagedUser } from '../../types';

export default function AccessPage() {
  const [params, setParams] = useSearchParams();
  const userId = Number(params.get('user')) || null;

  const [users, setUsers] = useState<ManagedUser[]>([]);
  const [access, setAccess] = useState<AccessResponse | null>(null);
  const [checked, setChecked] = useState<Record<number, boolean>>({});

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
        setChecked(Object.fromEntries(res.pages.map((p) => [p.id, p.granted])));
      })
      .catch((e) => setError(errorMessage(e)))
      .finally(() => setLoadingAccess(false));
  }, [userId]);

  async function onSave() {
    if (!userId || !access) return;
    setSaving(true);
    setError(null);
    setSaved(false);

    // Hanya akses TAMBAHAN yang disimpan; halaman departemen/shared sudah otomatis.
    const ids = access.pages.filter((p) => !p.by_department && checked[p.id]).map((p) => p.id);

    try {
      await accessService.update(userId, ids);
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
          Halaman departemen dan shared terbuka otomatis. Centang halaman lain untuk memberi akses tambahan.
        </p>
      </div>

      <div className="toolbar">
        <select
          value={userId ?? ''}
          disabled={loadingUsers}
          onChange={(e) => setParams(e.target.value ? { user: e.target.value } : {})}
        >
          <option value="">Pilih user...</option>
          {users.map((u) => (
            <option key={u.id} value={u.id}>
              {u.name} ({u.department?.code ?? 'tanpa departemen'})
            </option>
          ))}
        </select>
      </div>

      {error && <Alert>{error}</Alert>}
      {saved && <Alert kind="success">Akses {selectedUser?.name ?? 'user'} berhasil disimpan.</Alert>}

      {!userId && <EmptyState>Pilih user untuk mengatur aksesnya.</EmptyState>}
      {loadingAccess && <Loader />}

      {access && !loadingAccess && (
        <div className="panel">
          {access.full_access && (
            <Alert kind="info">
              User ini punya full access (master atau departemen full-access), semua halaman otomatis terbuka.
            </Alert>
          )}

          <ul className="access-list">
            {access.pages.map((p) => (
              <li key={p.id} className={p.by_department ? 'access-item locked' : 'access-item'}>
                <label className="check">
                  <input
                    type="checkbox"
                    checked={p.by_department || !!checked[p.id]}
                    disabled={p.by_department}
                    onChange={(e) => setChecked({ ...checked, [p.id]: e.target.checked })}
                  />
                  <span>
                    <strong>{p.name}</strong> <code>{p.key}</code>
                  </span>
                </label>
                <span className="access-meta">
                  {!p.is_active && <span className="badge badge-off">Nonaktif</span>}
                  {p.department ? <span className="badge">{p.department.code}</span> : <span className="badge badge-accent">Shared</span>}
                  {p.by_department && <span className="muted">otomatis</span>}
                </span>
              </li>
            ))}
          </ul>

          <div className="modal-actions">
            <button type="button" className="btn btn-primary" onClick={() => void onSave()} disabled={saving}>
              {saving ? 'Menyimpan...' : 'Simpan akses'}
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
