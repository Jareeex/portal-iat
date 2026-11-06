import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Alert, Loader } from '../../components/common/Feedback';
import { trainingService } from '../../services/training.service';
import { errorMessage } from '../../utils/error';
import type { Status, TrainingDetailResponse, TrainingRecord } from './training';

interface TrainingCrewDetailPageProps {
  crewId: number;
  onDeleted: () => void;
}

const statusNames: Record<Status, string> = { expired: 'Expired', soon: 'Segera', valid: 'Berlaku' };

function formatDate(value: string | null): string {
  if (!value) return '–';

  const date = new Date(`${value}T00:00:00`);
  if (Number.isNaN(date.getTime())) return '–';

  return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
    .format(date);
}

function RecordRow({
  crewId,
  record,
  manage,
  onSaved,
}: {
  crewId: number;
  record: TrainingRecord;
  manage: boolean;
  onSaved: () => void;
}) {
  const [editing, setEditing] = useState(false);
  const [busy, setBusy] = useState(false);
  const [dates, setDates] = useState({
    last_check: record.last_check ?? '',
    next_check: record.next_check ?? '',
    plan_check: record.plan_check ?? '',
  });
  const [error, setError] = useState<string | null>(null);

  async function save(): Promise<void> {
    setBusy(true);
    setError(null);
    try {
      await trainingService.saveRecord(crewId, record.type_id, dates);
      setEditing(false);
      onSaved();
    } catch (cause) {
      setError(errorMessage(cause));
    } finally {
      setBusy(false);
    }
  }

  return (
    <>
      <tr>
        <td>{record.name}{record.validity && <div className="muted">{record.validity}</div>}</td>
        {editing ? (
          <>
            {(['last_check', 'next_check', 'plan_check'] as const).map((field) => (
              <td key={field}><input type="date" value={dates[field]} onChange={(event) => setDates({ ...dates, [field]: event.target.value })} /></td>
            ))}
            <td />
            <td className="training-row-actions">
              <button type="button" className="btn btn-primary" disabled={busy} onClick={() => void save()}>Simpan</button>
              <button type="button" className="btn btn-ghost" onClick={() => setEditing(false)}>Batal</button>
            </td>
          </>
        ) : (
          <>
            <td>{formatDate(record.last_check)}</td>
            <td>{formatDate(record.next_check)}</td>
            <td>{formatDate(record.plan_check)}</td>
            <td>{record.status ? <span className={`badge badge-${record.status}`}>{statusNames[record.status]}{record.days_left !== null ? ` · ${record.days_left} hari` : ''}</span> : '–'}</td>
            <td className="training-row-actions">{manage && <button type="button" className="btn btn-ghost" onClick={() => setEditing(true)}>Edit</button>}</td>
          </>
        )}
      </tr>
      {error && <tr><td colSpan={6}><Alert>{error}</Alert></td></tr>}
    </>
  );
}

export default function TrainingCrewDetailPage({ crewId, onDeleted }: TrainingCrewDetailPageProps) {
  const [data, setData] = useState<TrainingDetailResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      setData(await trainingService.show(crewId));
    } catch (cause) {
      setError(errorMessage(cause));
    } finally {
      setLoading(false);
    }
  }, [crewId]);

  useEffect(() => { void load(); }, [load]);

  async function remove(): Promise<void> {
    if (!data || !window.confirm(`Hapus ${data.crew.name} beserta semua record training?`)) return;
    try {
      await trainingService.remove(crewId);
      onDeleted();
    } catch (cause) {
      setError(errorMessage(cause));
    }
  }

  if (loading) return <Loader />;
  if (!data) return <section className="training-page">{error && <Alert>{error}</Alert>}</section>;

  return (
    <section className="training-page">
      <Link to="/training" className="training-back-link">← Semua crew</Link>
      <header className="page-head page-head-row">
        <div>
          <h2>{data.crew.name}{data.crew.code ? ` (${data.crew.code})` : ''}</h2>
          <p className="muted">{data.crew.group_label}{data.crew.position ? ` · ${data.crew.position}` : ''}</p>
          <div className="training-counts training-counts-start">
            <span className="badge badge-expired">{data.counts.expired} expired</span>
            <span className="badge badge-soon">{data.counts.soon} segera</span>
            <span className="badge badge-valid">{data.counts.valid} berlaku</span>
          </div>
        </div>
        {data.can.manage && (
          <div className="training-actions">
            <Link to={`/training/crews/${crewId}/edit`} className="btn btn-ghost">Edit data crew</Link>
            <button type="button" className="btn btn-danger" onClick={() => void remove()}>Hapus</button>
          </div>
        )}
      </header>

      {error && <Alert>{error}</Alert>}
      {data.personal && (
        <section className="panel training-personal">
          <h3>Data pribadi</h3>
          <dl>
            <div><dt>Tanggal lahir</dt><dd>{formatDate(data.personal.dob)}</dd></div>
            <div><dt>Kewarganegaraan</dt><dd>{data.personal.nationality || '–'}</dd></div>
            <div><dt>Bergabung di IAT</dt><dd>{formatDate(data.personal.joined_iat)}</dd></div>
            <div><dt>Lisensi / rating</dt><dd>{[data.personal.licence, data.personal.rating].filter(Boolean).join(' · ') || '–'}</dd></div>
            <div><dt>Paspor</dt><dd>{data.personal.passport_no || '–'}</dd></div>
            <div><dt>Paspor berlaku sampai</dt><dd>{formatDate(data.personal.passport_expiry)}</dd></div>
            <div><dt>Alamat / telepon</dt><dd>{data.personal.address || '–'}</dd></div>
            <div><dt>Catatan</dt><dd>{data.personal.remarks || '–'}</dd></div>
          </dl>
        </section>
      )}

      {data.sections.map((section) => (
        <section className="training-section" key={section.section}>
          <h3>{section.section}</h3>
          <div className="table-wrap">
            <table className="table training-table">
              <thead><tr><th>Item</th><th>Last check</th><th>Next check</th><th>Plan check</th><th>Status</th><th /></tr></thead>
              <tbody>{section.items.map((record) => (
                <RecordRow key={record.type_id} crewId={crewId} record={record} manage={data.can.manage} onSaved={() => void load()} />
              ))}</tbody>
            </table>
          </div>
        </section>
      ))}
    </section>
  );
}