import { useCallback, useEffect, useMemo, useRef, useState, type ChangeEvent } from 'react';
import { Link } from 'react-router-dom';
import { Alert, EmptyState, Loader } from '../../components/common/Feedback';
import { trainingService } from '../../services/training.service';
import { errorMessage } from '../../utils/error';
import type { CrewRecord, TrainingIndexResponse } from './training';

type StatusFilter = 'all' | 'expired' | 'soon';

function StatusBar({ crew }: { crew: CrewRecord }) {
  const total = crew.expired + crew.soon + crew.valid || 1;

  return (
    <div className="training-bar" aria-label={`${crew.expired} expired, ${crew.soon} segera, ${crew.valid} berlaku`}>
      <span className="training-bar-expired" style={{ width: `${(crew.expired / total) * 100}%` }} />
      <span className="training-bar-soon" style={{ width: `${(crew.soon / total) * 100}%` }} />
      <span className="training-bar-valid" style={{ width: `${(crew.valid / total) * 100}%` }} />
    </div>
  );
}

export default function TrainingIndexPage() {
  const [data, setData] = useState<TrainingIndexResponse | null>(null);
  const [query, setQuery] = useState('');
  const [group, setGroup] = useState('all');
  const [status, setStatus] = useState<StatusFilter>('all');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const fileInput = useRef<HTMLInputElement>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      setData(await trainingService.index());
    } catch (cause) {
      setError(errorMessage(cause));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { void load(); }, [load]);

  const crews = useMemo(() => {
    const term = query.trim().toLocaleLowerCase('id-ID');
    return (data?.crews ?? []).filter((crew) => {
      if (group !== 'all' && crew.group !== group) return false;
      if (status === 'expired' && crew.expired === 0) return false;
      if (status === 'soon' && crew.soon === 0) return false;
      return !term || `${crew.name} ${crew.code ?? ''}`.toLocaleLowerCase('id-ID').includes(term);
    });
  }, [data, group, query, status]);

  async function downloadCsv(): Promise<void> {
    try {
      const file = await trainingService.exportCsv(group === 'all' ? undefined : group);
      const url = URL.createObjectURL(file);
      const anchor = document.createElement('a');
      anchor.href = url;
      anchor.download = 'training-record.csv';
      anchor.click();
      URL.revokeObjectURL(url);
    } catch (cause) {
      setError(errorMessage(cause));
    }
  }

  async function importCsv(event: ChangeEvent<HTMLInputElement>): Promise<void> {
    const file = event.target.files?.[0];
    if (!file) return;
    try {
      const result = await trainingService.importCsv(file);
      setMessage(result.errors.length ? `${result.message} ${result.errors.join('; ')}` : result.message);
      await load();
    } catch (cause) {
      setError(errorMessage(cause));
    } finally {
      event.target.value = '';
    }
  }

  if (loading) return <Loader />;

  return (
    <section className="training-page">
      <header className="page-head page-head-row">
        <div>
          <span className="eyebrow training-eyebrow">TRAINING DEPARTMENT</span>
          <h2>Training Record Crew</h2>
          <p className="muted">Data per {data?.period ?? '-'}</p>
        </div>
        {data?.can.manage && (
          <div className="training-actions">
            <button type="button" className="btn btn-ghost" onClick={() => void downloadCsv()}>Export CSV</button>
            <button type="button" className="btn btn-ghost" onClick={() => fileInput.current?.click()}>Import CSV</button>
            <input ref={fileInput} className="training-file-input" type="file" accept=".csv,.txt" onChange={(event) => void importCsv(event)} />
            <Link to="/training/crews/create" className="btn btn-primary">+ Tambah crew</Link>
          </div>
        )}
      </header>

      {error && <Alert>{error}</Alert>}
      {message && <Alert kind="success">{message}</Alert>}

      {data && (
        <>
          <div className="training-stats">
            <div className="training-stat"><span className="training-stat-value">{data.summary.crew}</span><span className="muted">Crew</span></div>
            <div className="training-stat training-stat-expired"><span className="training-stat-value">{data.summary.expired}</span><span className="muted">Training expired</span></div>
            <div className="training-stat training-stat-soon"><span className="training-stat-value">{data.summary.soon}</span><span className="muted">Jatuh tempo ≤ 60 hari</span></div>
            <div className="training-stat training-stat-valid"><span className="training-stat-value">{data.summary.valid}</span><span className="muted">Masih berlaku</span></div>
          </div>

          <div className="training-controls">
            <input type="search" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Cari nama atau kode crew" aria-label="Cari nama atau kode crew" />
            <select value={group} onChange={(event) => setGroup(event.target.value)} aria-label="Filter kelompok">
              <option value="all">Semua kelompok</option>
              {data.groups.map((option) => <option key={option.key} value={option.key}>{option.label}</option>)}
            </select>
            <select value={status} onChange={(event) => setStatus(event.target.value as StatusFilter)} aria-label="Filter status">
              <option value="all">Semua status</option>
              <option value="expired">Ada yang expired</option>
              <option value="soon">Segera jatuh tempo</option>
            </select>
          </div>

          <div className="training-results-head"><strong>{crews.length} crew</strong><span className="muted">Pilih crew untuk membuka record detail</span></div>
          {crews.length === 0 ? (
            <EmptyState>{error ? 'Data training tidak dapat dimuat.' : 'Tidak ada crew yang cocok dengan filter.'}</EmptyState>
          ) : (
            <div className="training-list">
              {crews.map((crew) => (
                <Link className="training-crew-summary training-crew-link" key={crew.id} to={`/training/crews/${crew.id}`}>
                  <span className="training-crew-name">
                    <strong>{crew.name}</strong>
                    <span className="muted">{[crew.code, crew.group_label, crew.position].filter(Boolean).join(' · ')}</span>
                  </span>
                  <StatusBar crew={crew} />
                  <span className="training-counts">
                    {crew.expired > 0 && <span className="badge badge-danger">{crew.expired} expired</span>}
                    {crew.soon > 0 && <span className="badge badge-warning">{crew.soon} segera</span>}
                    {crew.valid > 0 && <span className="badge badge-ok">{crew.valid} berlaku</span>}
                  </span>
                  <span className="training-chevron" aria-hidden="true">›</span>
                </Link>
              ))}
            </div>
          )}
          <p className="training-footnote">Status dihitung dari tanggal next check. “Segera” berarti jatuh tempo dalam 60 hari.</p>
        </>
      )}
    </section>
  );
}