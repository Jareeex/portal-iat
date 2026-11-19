import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { Alert, Loader } from '../../components/common/Feedback';
import { Field } from '../../components/common/Field';
import { Modal } from '../../components/common/Modal';
import { DataTable, type Column } from '../../components/tables/DataTable';
import { Pagination } from '../../components/tables/Pagination';
import { usePermission } from '../../hooks/usePermission';
import { attendanceService } from '../../services/attendance.service';
import { errorMessage } from '../../utils/error';
import type {
    AttendanceGeofence,
    AttendanceRecord,
    AttendanceUserOption,
} from '../../types';

interface AttendancePreview {
    url: string;
    title: string;
}

function currentDateInJakarta(): string {
    return new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Jakarta',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date());
}

function formatTime(value: string | null, timezone: string): string {
    if (!value) return '—';

    return new Intl.DateTimeFormat('id-ID', {
        timeZone: timezone,
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function formatClockInput(value: string | null, timezone: string): string {
    if (!value) return '';

    const parts = new Intl.DateTimeFormat('en-GB', {
        timeZone: timezone,
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).formatToParts(new Date(value));
    const hour = parts.find((part) => part.type === 'hour')?.value ?? '00';
    const minute = parts.find((part) => part.type === 'minute')?.value ?? '00';

    return `${hour}:${minute}`;
}

function formatAttendanceDate(value: string, timezone: string): string {
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeZone: timezone,
    }).format(new Date(`${value}T00:00:00Z`));
}

function formatOffsiteReason(
    reason: string | null,
    details: string | null,
    labels: Record<string, string>,
): string | null {
    if (!reason) return null;

    const label = labels[reason] ?? reason;

    return details ? `${label}: ${details}` : label;
}

export default function AttendancePage() {
    const { isMaster, canEdit } = usePermission();
    const canManage = isMaster || canEdit('hr-employees');
    const [date, setDate] = useState(currentDateInJakarta);
    const [queryInput, setQueryInput] = useState('');
    const [query, setQuery] = useState('');
    const [page, setPage] = useState(1);
    const [timezone, setTimezone] = useState('Asia/Jakarta');
    const [geofence, setGeofence] = useState<AttendanceGeofence | null>(null);
    const [radiusInput, setRadiusInput] = useState('');
    const [settingsSaving, setSettingsSaving] = useState(false);
    const [settingsError, setSettingsError] = useState<string | null>(null);
    const [settingsSuccess, setSettingsSuccess] = useState<string | null>(null);
    const [actionSuccess, setActionSuccess] = useState<string | null>(null);
    const [records, setRecords] = useState<AttendanceRecord[]>([]);
    const [users, setUsers] = useState<AttendanceUserOption[]>([]);
    const [lastPage, setLastPage] = useState(1);
    const [total, setTotal] = useState(0);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [editing, setEditing] = useState<AttendanceRecord | null>(null);
    const [editForm, setEditForm] = useState({
        user_id: '',
        attendance_date: '',
        clock_in_time: '',
        clock_out_time: '',
    });
    const [editError, setEditError] = useState<string | null>(null);
    const [editSaving, setEditSaving] = useState(false);
    const [manualOpen, setManualOpen] = useState(false);
    const [manualForm, setManualForm] = useState({
        user_id: '',
        attendance_date: date,
        clock_in_time: '',
        clock_out_time: '',
    });
    const [manualError, setManualError] = useState<string | null>(null);
    const [manualSaving, setManualSaving] = useState(false);
    const [deleting, setDeleting] = useState<number | null>(null);
    const [photoLoading, setPhotoLoading] = useState<string | null>(null);
    const [preview, setPreview] = useState<AttendancePreview | null>(null);

    const load = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const result = await attendanceService.list({
                date,
                q: canManage ? query || undefined : undefined,
                page,
            });
            setGeofence(result.geofence);
            setRadiusInput(String(result.geofence.radius_meters));
            setRecords(result.records.data);
            setUsers(result.users);
            setLastPage(result.records.last_page);
            setTotal(result.records.total);
            setTimezone(result.timezone);
        } catch (loadError) {
            setError(errorMessage(loadError));
        } finally {
            setLoading(false);
        }
    }, [canManage, date, page, query]);

    useEffect(() => {
        void load();
    }, [load]);

    useEffect(
        () => () => {
            if (preview) URL.revokeObjectURL(preview.url);
        },
        [preview],
    );

    async function submitSettings(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setSettingsError(null);
        setSettingsSuccess(null);

        const radiusMeters = Number(radiusInput);
        if (!Number.isInteger(radiusMeters) || radiusMeters < 1 || radiusMeters > 100000) {
            setSettingsError('Radius harus berupa bilangan bulat antara 1 dan 100.000 meter.');

            return;
        }

        setSettingsSaving(true);
        try {
            const updatedSettings =
                await attendanceService.updateGeofenceRadius(radiusMeters);
            setGeofence((current) =>
                current ? { ...current, ...updatedSettings } : current,
            );
            setRadiusInput(String(updatedSettings.radius_meters));
            setSettingsSuccess('Radius lokasi kerja berhasil diperbarui.');
        } catch (saveError) {
            setSettingsError(errorMessage(saveError));
        } finally {
            setSettingsSaving(false);
        }
    }

    async function openPhoto(
        record: AttendanceRecord,
        action: 'clock-in' | 'clock-out',
    ) {
        const key = `${record.id}-${action}`;
        setPhotoLoading(key);
        setError(null);
        try {
            const blob = await attendanceService.photo(record.id, action);
            setPreview({
                url: URL.createObjectURL(blob),
                title: `${record.employee.name} · ${action === 'clock-in' ? 'Clock-in' : 'Clock-out'}`,
            });
        } catch (photoError) {
            setError(errorMessage(photoError));
        } finally {
            setPhotoLoading(null);
        }
    }

    function openEdit(record: AttendanceRecord) {
        setEditing(record);
        setEditForm({
            user_id: record.employee.user_id?.toString() ?? '',
            attendance_date: record.attendance_date,
            clock_in_time: formatClockInput(record.clock_in_at, timezone),
            clock_out_time: formatClockInput(record.clock_out_at, timezone),
        });
        setEditError(null);
        setActionSuccess(null);
    }

    async function submitEdit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!editing) return;

        setEditSaving(true);
        setEditError(null);
        try {
            await attendanceService.update(editing.id, {
                user_id: Number(editForm.user_id),
                attendance_date: editForm.attendance_date,
                clock_in_time: editForm.clock_in_time,
                clock_out_time: editForm.clock_out_time || null,
            });
            setEditing(null);
            setActionSuccess('Data attendance berhasil diperbarui.');
            await load();
        } catch (saveError) {
            setEditError(errorMessage(saveError));
        } finally {
            setEditSaving(false);
        }
    }

    function openManualEntry() {
        setManualForm({
            user_id: '',
            attendance_date: date,
            clock_in_time: '',
            clock_out_time: '',
        });
        setManualError(null);
        setActionSuccess(null);
        setManualOpen(true);
    }

    async function submitManualEntry(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setManualSaving(true);
        setManualError(null);
        setActionSuccess(null);
        try {
            await attendanceService.createManual({
                user_id: Number(manualForm.user_id),
                attendance_date: manualForm.attendance_date,
                clock_in_time: manualForm.clock_in_time,
                clock_out_time: manualForm.clock_out_time || null,
            });
            setManualOpen(false);
            setActionSuccess('Attendance manual berhasil ditambahkan.');
            await load();
        } catch (saveError) {
            setManualError(errorMessage(saveError));
        } finally {
            setManualSaving(false);
        }
    }

    async function deleteAttendance(record: AttendanceRecord) {
        if (!window.confirm(`Hapus attendance ${record.employee.name} tanggal ${record.attendance_date}?`)) {
            return;
        }

        setDeleting(record.id);
        setError(null);
        setActionSuccess(null);
        try {
            await attendanceService.remove(record.id);
            setActionSuccess('Data attendance berhasil dihapus.');
            await load();
        } catch (deleteError) {
            setError(errorMessage(deleteError));
        } finally {
            setDeleting(null);
        }
    }

    const columns: Column<AttendanceRecord>[] = [
        {
            header: 'Karyawan',
            render: (record: AttendanceRecord) => (
                <div>
                    <strong>{record.employee.name}</strong>
                    <div className="muted">
                        {record.employee.employee_number || 'No. pegawai belum diisi'}
                        {record.employee.department
                            ? ` · ${record.employee.department}`
                            : ''}
                    </div>
                </div>
            ),
        },
        {
            header: 'Tanggal',
            render: (record) =>
                formatAttendanceDate(record.attendance_date, timezone),
        },
        {
            header: 'Clock-in',
            render: (record) => (
                <div>
                    <strong>{formatTime(record.clock_in_at, timezone)}</strong>
                    {record.clock_in_is_offsite && (
                        <span className="badge badge-warning">On Leave</span>
                    )}
                    <div className="muted">
                        {record.clock_in_distance_meters === null
                            ? 'Lokasi tidak tercatat'
                            : `${record.clock_in_distance_meters} m dari lokasi kerja`}
                        {record.clock_in_is_offsite &&
                            record.clock_in_reason &&
                            ` · ${formatOffsiteReason(
                                record.clock_in_reason,
                                record.clock_in_reason_details,
                                geofence?.offsite_reasons ?? {},
                            )}`}
                    </div>
                    {record.clock_in_photo_available && (
                        <button
                            type="button"
                            className="btn btn-ghost attendance-photo-link"
                            disabled={photoLoading === `${record.id}-clock-in`}
                            onClick={() => void openPhoto(record, 'clock-in')}
                        >
                            {photoLoading === `${record.id}-clock-in`
                                ? 'Memuat...'
                                : 'Lihat foto'}
                        </button>
                    )}
                </div>
            ),
        },
        {
            header: 'Clock-out',
            render: (record) => (
                <div>
                    <strong>{formatTime(record.clock_out_at, timezone)}</strong>
                    {record.clock_out_is_offsite && (
                        <span className="badge badge-warning">On Leave</span>
                    )}
                    {record.clock_out_at && (
                        <div className="muted">
                            {record.clock_out_distance_meters === null
                                ? 'Lokasi tidak tercatat'
                                : `${record.clock_out_distance_meters} m dari lokasi kerja`}
                            {record.clock_out_is_offsite &&
                                record.clock_out_reason &&
                                ` · ${formatOffsiteReason(
                                    record.clock_out_reason,
                                    record.clock_out_reason_details,
                                    geofence?.offsite_reasons ?? {},
                                )}`}
                        </div>
                    )}
                    {record.clock_out_photo_available && (
                        <button
                            type="button"
                            className="btn btn-ghost attendance-photo-link"
                            disabled={photoLoading === `${record.id}-clock-out`}
                            onClick={() => void openPhoto(record, 'clock-out')}
                        >
                            {photoLoading === `${record.id}-clock-out`
                                ? 'Memuat...'
                                : 'Lihat foto'}
                        </button>
                    )}
                </div>
            ),
        },
        ...(canManage
            ? [
                  {
                      header: 'Aksi',
                      width: '170px',
                      render: (record: AttendanceRecord) => (
                          <div className="attendance-row-actions">
                              <button
                                  type="button"
                                  className="btn btn-ghost"
                                  onClick={() => openEdit(record)}
                              >
                                  Edit
                              </button>
                              <button
                                  type="button"
                                  className="btn btn-ghost"
                                  disabled={deleting === record.id}
                                  onClick={() => void deleteAttendance(record)}
                              >
                                  {deleting === record.id ? 'Menghapus...' : 'Hapus'}
                              </button>
                          </div>
                      ),
                  } satisfies Column<AttendanceRecord>,
              ]
            : []),
    ];

    return (
        <div>
            <div className="page-head">
                <h2>{canManage ? 'Pengelolaan Attendance' : 'Attendances'}</h2>
                {canManage ? (
                    <p className="muted">
                        HR dan Master dapat mengelola seluruh data attendance.
                    </p>
                ) : (
                    <p className="muted">
                        Riwayat attendance Anda. Pengisian absensi tersedia melalui aplikasi mobile.
                    </p>
                )}
            </div>

            {error && <Alert>{error}</Alert>}
            {actionSuccess && <Alert kind="success">{actionSuccess}</Alert>}

            {canManage && (
                <section className="panel">
                    <h3>Lokasi kerja</h3>
                    <p className="muted">
                        {geofence
                            ? `Koordinat tetap: ${geofence.latitude.toFixed(6)}, ${geofence.longitude.toFixed(6)}.`
                            : 'Memuat koordinat lokasi kerja...'}{' '}
                        On Leave otomatis tercatat jika absensi dilakukan di luar
                        radius dan akun karyawan telah diaktifkan Master.
                    </p>
                    {settingsError && <Alert>{settingsError}</Alert>}
                    {settingsSuccess && (
                        <Alert kind="success">{settingsSuccess}</Alert>
                    )}
                    <form className="toolbar" onSubmit={submitSettings}>
                        <Field label="Radius absensi (meter)">
                            <input
                                type="number"
                                min={1}
                                max={100000}
                                step={1}
                                value={radiusInput}
                                onChange={(event) =>
                                    setRadiusInput(event.target.value)
                                }
                                required
                                disabled={settingsSaving || geofence === null}
                            />
                        </Field>
                        <button
                            type="submit"
                            className="btn btn-primary"
                            disabled={settingsSaving || geofence === null}
                        >
                            {settingsSaving ? 'Menyimpan...' : 'Simpan radius'}
                        </button>
                    </form>
                </section>
            )}

            <section className="panel attendance-records">
                <div className="page-head page-head-row">
                    <div>
                        <h3>{canManage ? 'Data absensi karyawan' : 'Riwayat attendance saya'}</h3>
                        <p className="muted">
                            {canManage
                                ? 'Buat, koreksi, atau hapus catatan attendance karyawan.'
                                : 'Data yang ditampilkan hanya attendance milik Anda.'}
                        </p>
                    </div>
                    {canManage && (
                        <button
                            type="button"
                            className="btn btn-primary"
                            onClick={openManualEntry}
                        >
                            Tambah attendance manual
                        </button>
                    )}
                </div>

                <form
                    className="toolbar attendance-toolbar"
                    onSubmit={(event) => {
                        event.preventDefault();
                        setPage(1);
                        setQuery(queryInput.trim());
                    }}
                >
                    <Field label="Tanggal">
                        <input
                            type="date"
                            value={date}
                            onChange={(event) => {
                                setPage(1);
                                setDate(event.target.value);
                            }}
                        />
                    </Field>
                    {canManage && (
                        <>
                            <Field label="Cari karyawan">
                                <input
                                    type="search"
                                    placeholder="Nama atau no. pegawai"
                                    value={queryInput}
                                    onChange={(event) =>
                                        setQueryInput(event.target.value)
                                    }
                                />
                            </Field>
                            <button type="submit" className="btn">
                                Cari
                            </button>
                        </>
                    )}
                </form>

                {loading ? (
                    <Loader />
                ) : (
                    <>
                        <DataTable
                            columns={columns}
                            rows={records}
                            rowKey={(record) => record.id}
                            empty="Belum ada data absensi pada tanggal ini."
                        />
                        <Pagination
                            page={page}
                            lastPage={lastPage}
                            total={total}
                            onChange={setPage}
                        />
                    </>
                )}
            </section>

            {manualOpen && (
                <Modal
                    title="Tambah attendance manual"
                    onClose={() => setManualOpen(false)}
                >
                    <form className="form" onSubmit={submitManualEntry}>
                        {manualError && <Alert>{manualError}</Alert>}
                        {users.length === 0 && (
                            <Alert kind="info">
                                Belum ada user dengan profil karyawan yang dapat dipilih.
                            </Alert>
                        )}
                        <Field label="Karyawan">
                            <select
                                value={manualForm.user_id}
                                onChange={(event) =>
                                    setManualForm({
                                        ...manualForm,
                                        user_id: event.target.value,
                                    })
                                }
                                required
                            >
                                <option value="">Pilih karyawan</option>
                                {users.map((user) => (
                                    <option key={user.id} value={user.id}>
                                        {user.name}
                                        {user.employee_number
                                            ? ` · ${user.employee_number}`
                                            : ''}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Tanggal attendance">
                            <input
                                type="date"
                                value={manualForm.attendance_date}
                                onChange={(event) =>
                                    setManualForm({
                                        ...manualForm,
                                        attendance_date: event.target.value,
                                    })
                                }
                                required
                            />
                        </Field>
                        <Field label="Jam clock-in">
                            <input
                                type="time"
                                value={manualForm.clock_in_time}
                                onChange={(event) =>
                                    setManualForm({
                                        ...manualForm,
                                        clock_in_time: event.target.value,
                                    })
                                }
                                required
                            />
                        </Field>
                        <Field label="Jam clock-out (opsional)">
                            <input
                                type="time"
                                value={manualForm.clock_out_time}
                                onChange={(event) =>
                                    setManualForm({
                                        ...manualForm,
                                        clock_out_time: event.target.value,
                                    })
                                }
                            />
                        </Field>
                        <div className="modal-actions">
                            <button
                                type="button"
                                className="btn btn-ghost"
                                onClick={() => setManualOpen(false)}
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                className="btn btn-primary"
                                disabled={manualSaving || users.length === 0}
                            >
                                {manualSaving ? 'Menyimpan...' : 'Simpan attendance'}
                            </button>
                        </div>
                    </form>
                </Modal>
            )}

            {editing && (
                <Modal
                    title={`Edit attendance · ${editing.employee.name}`}
                    onClose={() => setEditing(null)}
                >
                    <form className="form" onSubmit={submitEdit}>
                        {editError && <Alert>{editError}</Alert>}
                        <Field label="Karyawan">
                            <select
                                value={editForm.user_id}
                                onChange={(event) =>
                                    setEditForm({
                                        ...editForm,
                                        user_id: event.target.value,
                                    })
                                }
                                required
                            >
                                <option value="">Pilih karyawan</option>
                                {users.map((user) => (
                                    <option key={user.id} value={user.id}>
                                        {user.name}
                                        {user.employee_number
                                            ? ` · ${user.employee_number}`
                                            : ''}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Tanggal attendance">
                            <input
                                type="date"
                                value={editForm.attendance_date}
                                onChange={(event) =>
                                    setEditForm({
                                        ...editForm,
                                        attendance_date: event.target.value,
                                    })
                                }
                                required
                            />
                        </Field>
                        <Field label="Jam clock-in">
                            <input
                                type="time"
                                value={editForm.clock_in_time}
                                onChange={(event) =>
                                    setEditForm({
                                        ...editForm,
                                        clock_in_time: event.target.value,
                                    })
                                }
                                required
                            />
                        </Field>
                        <Field label="Jam clock-out">
                            <input
                                type="time"
                                value={editForm.clock_out_time}
                                onChange={(event) =>
                                    setEditForm({
                                        ...editForm,
                                        clock_out_time: event.target.value,
                                    })
                                }
                            />
                        </Field>
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
                                disabled={editSaving}
                            >
                                {editSaving ? 'Menyimpan...' : 'Simpan koreksi'}
                            </button>
                        </div>
                    </form>
                </Modal>
            )}

            {preview && (
                <Modal
                    title={preview.title}
                    onClose={() => setPreview(null)}
                >
                    <img
                        className="attendance-photo-preview"
                        src={preview.url}
                        alt={preview.title}
                    />
                </Modal>
            )}
        </div>
    );
}
