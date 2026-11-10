import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { Alert, Loader } from '../../components/common/Feedback';
import { Field } from '../../components/common/Field';
import { Modal } from '../../components/common/Modal';
import { DataTable, type Column } from '../../components/tables/DataTable';
import { Pagination } from '../../components/tables/Pagination';
import { usePermission } from '../../hooks/usePermission';
import { leaveService } from '../../services/leave.service';
import { errorMessage } from '../../utils/error';
import type { LeaveRequest, LeaveStatus, LeaveType } from '../../types';

type Decision = 'approve' | 'reject';

const statusLabels: Record<LeaveStatus, string> = {
    manager_pending: 'Menunggu persetujuan approver',
    hr_pending: 'Menunggu persetujuan HR',
    manager_rejected: 'Ditolak approver',
    approved: 'Disetujui',
    rejected: 'Ditolak',
    cancelled: 'Dibatalkan',
};

function statusClass(status: LeaveStatus): string {
    if (status === 'approved') return 'badge badge-ok';
    if (status === 'rejected') return 'badge badge-danger';
    if (status === 'manager_pending' || status === 'hr_pending')
        return 'badge badge-warning';
    return 'badge badge-off';
}

function formatDate(date: string): string {
    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${date}T00:00:00`));
}

export default function LeaveRequestsPage() {
    const { isMaster, canEdit } = usePermission();
    const canManage = isMaster || canEdit('hr-employees');
    const [types, setTypes] = useState<LeaveType[]>([]);
    const [typesLoading, setTypesLoading] = useState(true);
    const [typesError, setTypesError] = useState<string | null>(null);
    const [managers, setManagers] = useState<
        Awaited<ReturnType<typeof leaveService.managers>>
    >([]);
    const [managerListError, setManagerListError] = useState<string | null>(
        null,
    );
    const [submitting, setSubmitting] = useState(false);
    const [submitError, setSubmitError] = useState<string | null>(null);
    const [success, setSuccess] = useState<string | null>(null);

    const [requests, setRequests] = useState<LeaveRequest[]>([]);
    const [pendingCount, setPendingCount] = useState(0);
    const [page, setPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [total, setTotal] = useState(0);
    const [statusFilter, setStatusFilter] = useState('hr_pending');
    const [searchInput, setSearchInput] = useState('');
    const [search, setSearch] = useState('');
    const [inboxLoading, setInboxLoading] = useState(false);
    const [inboxError, setInboxError] = useState<string | null>(null);
    const [reviewing, setReviewing] = useState<LeaveRequest | null>(null);
    const [decision, setDecision] = useState<Decision>('approve');
    const [reviewNote, setReviewNote] = useState('');
    const [reviewError, setReviewError] = useState<string | null>(null);
    const [reviewSaving, setReviewSaving] = useState(false);
    const [managerReviewing, setManagerReviewing] = useState(false);
    const [managerRequests, setManagerRequests] = useState<LeaveRequest[]>([]);
    const [managerInboxLoading, setManagerInboxLoading] = useState(false);
    const [managerInboxError, setManagerInboxError] = useState<string | null>(
        null,
    );
    const [editing, setEditing] = useState<LeaveRequest | null>(null);
    const [editForm, setEditForm] = useState({
        leave_type_id: '',
        start_date: '',
        end_date: '',
        reason: '',
        contact_phone: '',
    });
    const [editError, setEditError] = useState<string | null>(null);
    const [editSaving, setEditSaving] = useState(false);
    const [downloadingId, setDownloadingId] = useState<number | null>(null);

    const loadTypes = useCallback(async () => {
        setTypesLoading(true);
        setTypesError(null);
        try {
            setTypes(await leaveService.types());
        } catch (loadError) {
            setTypesError(errorMessage(loadError));
        } finally {
            setTypesLoading(false);
        }
    }, []);

    const loadManagers = useCallback(async () => {
        setManagerListError(null);
        try {
            setManagers(await leaveService.managers());
        } catch (loadError) {
            setManagerListError(errorMessage(loadError));
        }
    }, []);

    const loadManagerInbox = useCallback(async () => {
        setManagerInboxLoading(true);
        setManagerInboxError(null);
        try {
            const result = await leaveService.managerInbox();
            setManagerRequests(result.data);
        } catch (loadError) {
            setManagerInboxError(errorMessage(loadError));
        } finally {
            setManagerInboxLoading(false);
        }
    }, []);

    const loadInbox = useCallback(async () => {
        if (!canManage) return;

        setInboxLoading(true);
        setInboxError(null);
        try {
            const [result, count] = await Promise.all([
                leaveService.inbox({
                    page,
                    status: statusFilter,
                    q: search || undefined,
                }),
                leaveService.pendingCount(),
            ]);
            setRequests(result.data);
            setLastPage(result.last_page);
            setTotal(result.total);
            setPendingCount(count);
        } catch (loadError) {
            setInboxError(errorMessage(loadError));
        } finally {
            setInboxLoading(false);
        }
    }, [canManage, page, search, statusFilter]);

    useEffect(() => {
        void loadTypes();
    }, [loadTypes]);

    useEffect(() => {
        void loadManagers();
        void loadManagerInbox();
    }, [loadManagers, loadManagerInbox]);

    useEffect(() => {
        void loadInbox();
    }, [loadInbox]);

    async function submitRequest(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setSubmitting(true);
        setSubmitError(null);
        setSuccess(null);

        const form = event.currentTarget;
        const formData = new FormData(form);
        try {
            await leaveService.submit(formData);
            setSuccess('Pengajuan terkirim ke manager untuk persetujuan awal.');
            form.reset();
            await Promise.all([
                loadManagerInbox(),
                canManage ? loadInbox() : Promise.resolve(),
            ]);
        } catch (submitFailure) {
            setSubmitError(errorMessage(submitFailure));
        } finally {
            setSubmitting(false);
        }
    }

    function openReview(
        request: LeaveRequest,
        nextDecision: Decision,
        fromManagerInbox = false,
    ) {
        setReviewing(request);
        setDecision(nextDecision);
        setManagerReviewing(fromManagerInbox);
        setReviewNote('');
        setReviewError(null);
    }

    async function submitReview(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!reviewing) return;

        setReviewSaving(true);
        setReviewError(null);
        try {
            await leaveService.review(
                reviewing.id,
                decision,
                reviewNote.trim(),
                managerReviewing,
            );
            setReviewing(null);
            setManagerReviewing(false);
            await Promise.all([
                loadManagerInbox(),
                canManage ? loadInbox() : Promise.resolve(),
            ]);
        } catch (reviewFailure) {
            setReviewError(errorMessage(reviewFailure));
        } finally {
            setReviewSaving(false);
        }
    }

    function openEdit(request: LeaveRequest) {
        setEditing(request);
        setEditForm({
            leave_type_id: String(request.leave_type.id),
            start_date: request.start_date,
            end_date: request.end_date,
            reason: request.reason,
            contact_phone: request.contact_phone ?? '',
        });
        setEditError(null);
    }

    async function submitEdit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!editing) return;

        setEditSaving(true);
        setEditError(null);
        try {
            await leaveService.update(editing.id, {
                ...editForm,
                leave_type_id: Number(editForm.leave_type_id),
                contact_phone: editForm.contact_phone.trim() || null,
            });
            setEditing(null);
            await loadInbox();
        } catch (editFailure) {
            setEditError(errorMessage(editFailure));
        } finally {
            setEditSaving(false);
        }
    }

    async function downloadAttachment(request: LeaveRequest) {
        setDownloadingId(request.id);
        setInboxError(null);
        try {
            const blob = await leaveService.attachment(request.id);
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `leave-request-${request.id}`;
            link.click();
            URL.revokeObjectURL(url);
        } catch (downloadError) {
            setInboxError(errorMessage(downloadError));
        } finally {
            setDownloadingId(null);
        }
    }

    const columns: Column<LeaveRequest>[] = [
        {
            header: 'Karyawan',
            render: (request) => (
                <div>
                    <strong>{request.employee.name ?? 'Akun nonaktif'}</strong>
                    <div className="muted">
                        {request.employee.employee_number ||
                            'No. pegawai belum diisi'}
                        {request.employee.department
                            ? ` · ${request.employee.department}`
                            : ''}
                    </div>
                </div>
            ),
        },
        {
            header: 'Jenis',
            render: (request) => request.leave_type.name,
        },
        {
            header: 'Periode',
            render: (request) => (
                <div>
                    {formatDate(request.start_date)} –{' '}
                    {formatDate(request.end_date)}
                    <div className="muted">{request.days} hari kerja</div>
                </div>
            ),
        },
        {
            header: 'Alasan',
            render: (request) => (
                <div>
                    <span>{request.reason}</span>
                    {request.manager_review_note && (
                        <div className="muted">
                            Catatan approver: {request.manager_review_note}
                        </div>
                    )}
                    {request.review_note && (
                        <div className="muted">
                            Catatan HR: {request.review_note}
                        </div>
                    )}
                    {request.attachment_url && (
                        <button
                            type="button"
                            className="btn btn-ghost"
                            disabled={downloadingId === request.id}
                            onClick={() => void downloadAttachment(request)}
                        >
                            {downloadingId === request.id
                                ? 'Mengunduh...'
                                : 'Unduh lampiran'}
                        </button>
                    )}
                </div>
            ),
        },
        {
            header: 'Status',
            render: (request) => (
                <span className={statusClass(request.status)}>
                    {statusLabels[request.status]}
                </span>
            ),
        },
        ...(canManage
            ? [
                  {
                      header: 'Aksi',
                      width: '190px',
                      render: (request: LeaveRequest) => (
                          <div className="row-actions">
                              <button
                                  type="button"
                                  className="btn btn-ghost"
                                  onClick={() => openEdit(request)}
                              >
                                  Edit
                              </button>
                              {request.status === 'hr_pending' && (
                                  <button
                                      type="button"
                                      className="btn btn-primary"
                                      onClick={() =>
                                          openReview(request, 'approve')
                                      }
                                  >
                                      Proses
                                  </button>
                              )}
                          </div>
                      ),
                  },
              ]
            : []),
    ];

    return (
        <div>
            <div className="page-head">
                <h2>Leave Request</h2>
                <p className="muted">
                    Manager mengajukan ke BOD, Officer dapat memilih approver
                    lintas departemen, dan jabatan lain memilih approver
                    departemennya. Setelah disetujui, pengajuan diteruskan ke
                    HR untuk keputusan akhir.
                </p>
            </div>

            {typesError && <Alert>{typesError}</Alert>}
            {submitError && <Alert>{submitError}</Alert>}
            {success && <Alert kind="success">{success}</Alert>}

            <section className="panel leave-panel">
                <h3>Form pengajuan</h3>
                {typesLoading ? (
                    <Loader />
                ) : (
                    <form className="form" onSubmit={submitRequest}>
                        <Field label="Jenis cuti">
                            <select
                                name="leave_type_id"
                                required
                                defaultValue=""
                            >
                                <option value="" disabled>
                                    Pilih jenis cuti
                                </option>
                                {types.map((type) => (
                                    <option key={type.id} value={type.id}>
                                        {type.name}
                                        {type.annual_quota_days !== null
                                            ? ` · kuota ${type.annual_quota_days} hari/tahun`
                                            : ''}
                                        {type.requires_attachment
                                            ? ' · lampiran wajib'
                                            : ''}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Approver cuti">
                            <select
                                name="manager_id"
                                required
                                defaultValue=""
                                disabled={managers.length === 0}
                            >
                                <option value="" disabled>
                                    {managers.length === 0
                                        ? 'Approver cuti departemen belum tersedia'
                                        : 'Pilih approver cuti departemen'}
                                </option>
                                {managers.map((manager) => (
                                    <option key={manager.id} value={manager.id}>
                                        {manager.name} · {manager.job_title}
                                    </option>
                                ))}
                            </select>
                            {managerListError && (
                                <span className="field-hint">
                                    {managerListError}
                                </span>
                            )}
                        </Field>
                        <div className="form-row">
                            <Field label="Tanggal mulai">
                                <input name="start_date" type="date" required />
                            </Field>
                            <Field label="Tanggal selesai">
                                <input name="end_date" type="date" required />
                            </Field>
                        </div>
                        <Field label="Alasan">
                            <textarea
                                name="reason"
                                rows={4}
                                maxLength={1000}
                                required
                            />
                        </Field>
                        <Field label="Nomor kontak selama cuti">
                            <input name="contact_phone" maxLength={30} />
                        </Field>
                        <Field label="Lampiran (PDF/JPG/PNG, maks. 4 MB)">
                            <input
                                name="attachment"
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png"
                            />
                        </Field>
                        {types.some((type) => type.requires_attachment) && (
                            <p className="field-hint">
                                Beberapa jenis cuti mewajibkan lampiran; lihat
                                persyaratan pada jenis yang dipilih.
                            </p>
                        )}
                        <div>
                            <button
                                type="submit"
                                className="btn btn-primary"
                                disabled={
                                    submitting ||
                                    types.length === 0 ||
                                    managers.length === 0
                                }
                            >
                                {submitting
                                    ? 'Mengirim...'
                                    : 'Kirim ke approver cuti'}
                            </button>
                        </div>
                    </form>
                )}
            </section>

            {(managerRequests.length > 0 || managerInboxError !== null) && (
                <section className="leave-inbox">
                    <div className="page-head page-head-row">
                        <div>
                            <h2>Persetujuan cuti</h2>
                            <p className="muted">
                                Pengajuan yang meminta persetujuan cuti darimu.
                            </p>
                        </div>
                    </div>
                    {managerInboxError && <Alert>{managerInboxError}</Alert>}
                    {managerInboxLoading ? (
                        <Loader />
                    ) : (
                        <DataTable
                            columns={[
                                {
                                    header: 'Karyawan',
                                    render: (request) => (
                                        <div>
                                            <strong>
                                                {request.employee.name}
                                            </strong>
                                            <div className="muted">
                                                {request.employee.department ??
                                                    '-'}
                                            </div>
                                        </div>
                                    ),
                                },
                                {
                                    header: 'Pengajuan',
                                    render: (request) => (
                                        <div>
                                            {request.leave_type.name}
                                            <div className="muted">
                                                {formatDate(request.start_date)}{' '}
                                                – {formatDate(request.end_date)}{' '}
                                                · {request.days} hari
                                            </div>
                                            <div>{request.reason}</div>
                                        </div>
                                    ),
                                },
                                {
                                    header: 'Lampiran',
                                    render: (request) =>
                                        request.attachment_url ? (
                                            <button
                                                type="button"
                                                className="btn btn-ghost"
                                                disabled={
                                                    downloadingId === request.id
                                                }
                                                onClick={() =>
                                                    void downloadAttachment(
                                                        request,
                                                    )
                                                }
                                            >
                                                Unduh
                                            </button>
                                        ) : (
                                            '-'
                                        ),
                                },
                                {
                                    header: 'Aksi',
                                    width: '120px',
                                    render: (request) => (
                                        <button
                                            type="button"
                                            className="btn btn-primary"
                                            onClick={() =>
                                                openReview(
                                                    request,
                                                    'approve',
                                                    true,
                                                )
                                            }
                                        >
                                            Proses
                                        </button>
                                    ),
                                },
                            ]}
                            rows={managerRequests}
                            rowKey={(request) => request.id}
                            empty="Tidak ada pengajuan yang menunggu persetujuanmu."
                        />
                    )}
                </section>
            )}

            {canManage && (
                <section className="leave-inbox">
                    <div className="page-head page-head-row">
                        <div>
                            <h2>Inbox HR</h2>
                            <p className="muted">
                                {pendingCount} pengajuan menunggu HR
                            </p>
                        </div>
                    </div>
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
                            placeholder="Cari nama / nomor pegawai"
                            value={searchInput}
                            onChange={(event) =>
                                setSearchInput(event.target.value)
                            }
                        />
                        <select
                            value={statusFilter}
                            onChange={(event) => {
                                setPage(1);
                                setStatusFilter(event.target.value);
                            }}
                        >
                            <option value="hr_pending">Menunggu HR</option>
                            <option value="approved">Disetujui</option>
                            <option value="rejected">Ditolak</option>
                            <option value="all">Semua status</option>
                        </select>
                        <button type="submit" className="btn">
                            Cari
                        </button>
                    </form>

                    {inboxError && <Alert>{inboxError}</Alert>}
                    {inboxLoading ? (
                        <Loader />
                    ) : (
                        <>
                            <DataTable
                                columns={columns}
                                rows={requests}
                                rowKey={(request) => request.id}
                                empty="Belum ada pengajuan cuti."
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
            )}

            {reviewing && (
                <Modal
                    title={`${managerReviewing ? 'Persetujuan manager' : 'Keputusan HR'} · ${decision === 'approve' ? 'Setujui' : 'Tolak'}`}
                    onClose={() => setReviewing(null)}
                >
                    <form className="form" onSubmit={submitReview}>
                        {reviewError && <Alert>{reviewError}</Alert>}
                        <p>
                            {reviewing.employee.name} ·{' '}
                            {reviewing.leave_type.name}
                            {' · '}
                            {formatDate(reviewing.start_date)} –{' '}
                            {formatDate(reviewing.end_date)}
                        </p>
                        <Field
                            label={
                                decision === 'reject'
                                    ? 'Alasan penolakan'
                                    : `${managerReviewing ? 'Catatan manager' : 'Catatan HR'} (opsional)`
                            }
                        >
                            <textarea
                                rows={3}
                                maxLength={1000}
                                value={reviewNote}
                                onChange={(event) =>
                                    setReviewNote(event.target.value)
                                }
                                required={decision === 'reject'}
                            />
                        </Field>
                        <div className="row-actions">
                            {decision === 'approve' ? (
                                <button
                                    type="submit"
                                    className="btn btn-primary"
                                    disabled={reviewSaving}
                                >
                                    {reviewSaving
                                        ? 'Menyimpan...'
                                        : managerReviewing
                                          ? 'Setujui & teruskan ke HR'
                                          : 'Setujui'}
                                </button>
                            ) : (
                                <button
                                    type="submit"
                                    className="btn btn-danger"
                                    disabled={reviewSaving}
                                >
                                    {reviewSaving ? 'Menyimpan...' : 'Tolak'}
                                </button>
                            )}
                            {decision === 'approve' ? (
                                <button
                                    type="button"
                                    className="btn btn-danger"
                                    onClick={() => setDecision('reject')}
                                >
                                    Tolak
                                </button>
                            ) : (
                                <button
                                    type="button"
                                    className="btn btn-ghost"
                                    onClick={() => setDecision('approve')}
                                >
                                    Kembali ke persetujuan
                                </button>
                            )}
                        </div>
                    </form>
                </Modal>
            )}

            {editing && (
                <Modal
                    title={`Edit pengajuan · ${editing.employee.name ?? 'Karyawan'}`}
                    onClose={() => setEditing(null)}
                >
                    <form className="form" onSubmit={submitEdit}>
                        {editError && <Alert>{editError}</Alert>}
                        <Field label="Jenis cuti">
                            <select
                                value={editForm.leave_type_id}
                                onChange={(event) =>
                                    setEditForm({
                                        ...editForm,
                                        leave_type_id: event.target.value,
                                    })
                                }
                                required
                            >
                                {types.map((type) => (
                                    <option key={type.id} value={type.id}>
                                        {type.name}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <div className="form-row">
                            <Field label="Tanggal mulai">
                                <input
                                    type="date"
                                    value={editForm.start_date}
                                    onChange={(event) =>
                                        setEditForm({
                                            ...editForm,
                                            start_date: event.target.value,
                                        })
                                    }
                                    required
                                />
                            </Field>
                            <Field label="Tanggal selesai">
                                <input
                                    type="date"
                                    value={editForm.end_date}
                                    onChange={(event) =>
                                        setEditForm({
                                            ...editForm,
                                            end_date: event.target.value,
                                        })
                                    }
                                    required
                                />
                            </Field>
                        </div>
                        <Field label="Alasan">
                            <textarea
                                rows={4}
                                maxLength={1000}
                                value={editForm.reason}
                                onChange={(event) =>
                                    setEditForm({
                                        ...editForm,
                                        reason: event.target.value,
                                    })
                                }
                                required
                            />
                        </Field>
                        <Field label="Nomor kontak selama cuti">
                            <input
                                maxLength={30}
                                value={editForm.contact_phone}
                                onChange={(event) =>
                                    setEditForm({
                                        ...editForm,
                                        contact_phone: event.target.value,
                                    })
                                }
                            />
                        </Field>
                        <div className="row-actions">
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
                                {editSaving
                                    ? 'Menyimpan...'
                                    : 'Simpan perubahan'}
                            </button>
                        </div>
                    </form>
                </Modal>
            )}
        </div>
    );
}
