import { useEffect, useMemo, useState } from 'react';
import { Alert, EmptyState, Loader } from '../../components/common/Feedback';
import { trainingService } from '../../services/training.service';
import { errorMessage } from '../../utils/error';
import type { Category, CrewRecord, Status, TrainingResponse } from './training';

type CategoryFilter = 'Semua' | Category;
type StatusFilter = 'all' | 'expired' | 'soon';

const categories: CategoryFilter[] = ['Semua', 'Pilot', 'Flight Attendant', 'FOO & HLO'];
const statusNames: Record<Status, string> = {
	expired: 'Expired',
	soon: 'Segera',
	valid: 'Berlaku',
};

function formatDate(value: string): string {
	return new Intl.DateTimeFormat('id-ID', {
		day: '2-digit',
		month: 'short',
		year: 'numeric',
	}).format(new Date(`${value}T00:00:00`));
}

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

export default function TrainingPage() {
	const [data, setData] = useState<TrainingResponse | null>(null);
	const [query, setQuery] = useState('');
	const [category, setCategory] = useState<CategoryFilter>('Semua');
	const [status, setStatus] = useState<StatusFilter>('all');
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState<string | null>(null);

	useEffect(() => {
		let active = true;

		trainingService
			.index()
			.then((response) => {
				if (active) setData(response);
			})
			.catch((cause) => {
				if (active) setError(errorMessage(cause));
			})
			.finally(() => {
				if (active) setLoading(false);
			});

		return () => {
			active = false;
		};
	}, []);

	const filteredCrews = useMemo(() => {
		const term = query.trim().toLocaleLowerCase('id-ID');

		return (data?.crews ?? []).filter((crew) => {
			if (category !== 'Semua' && crew.category !== category) return false;
			if (status === 'expired' && crew.expired === 0) return false;
			if (status === 'soon' && crew.soon === 0) return false;
			return !term || `${crew.name} ${crew.code ?? ''}`.toLocaleLowerCase('id-ID').includes(term);
		});
	}, [category, data, query, status]);

	if (loading) return <Loader />;

	return (
		<section className="training-page">
			<header className="page-head page-head-row">
				<div>
					<span className="eyebrow training-eyebrow">TRAINING DEPARTMENT</span>
					<h2>Training Record Crew</h2>
					<p className="muted">Status masa berlaku training per {data?.period ?? '-'}</p>
				</div>
				<span className="badge">Read only</span>
			</header>

			{error && <Alert>{error}</Alert>}

			{data && (
				<>
					<div className="training-stats">
						<div className="training-stat">
							<span className="training-stat-value">{data.summary.crew}</span>
							<span className="muted">Crew</span>
						</div>
						<div className="training-stat training-stat-expired">
							<span className="training-stat-value">{data.summary.expired}</span>
							<span className="muted">Training expired</span>
						</div>
						<div className="training-stat training-stat-soon">
							<span className="training-stat-value">{data.summary.soon}</span>
							<span className="muted">Jatuh tempo ≤ 60 hari</span>
						</div>
						<div className="training-stat training-stat-valid">
							<span className="training-stat-value">{data.summary.valid}</span>
							<span className="muted">Masih berlaku</span>
						</div>
					</div>

					<div className="training-controls">
						<input
							type="search"
							value={query}
							onChange={(event) => setQuery(event.target.value)}
							placeholder="Cari nama atau kode crew"
							aria-label="Cari nama atau kode crew"
						/>
						<select value={category} onChange={(event) => setCategory(event.target.value as CategoryFilter)} aria-label="Filter kategori">
							{categories.map((option) => <option key={option} value={option}>{option}</option>)}
						</select>
						<select value={status} onChange={(event) => setStatus(event.target.value as StatusFilter)} aria-label="Filter masa berlaku">
							<option value="all">Semua status</option>
							<option value="expired">Ada yang expired</option>
							<option value="soon">Segera jatuh tempo</option>
						</select>
					</div>

					<div className="training-results-head">
						<strong>{filteredCrews.length} crew</strong>
						<span className="muted">Pilih nama crew untuk melihat detail</span>
					</div>

					{filteredCrews.length === 0 ? (
						<EmptyState>{error ? 'Data training tidak dapat dimuat.' : 'Tidak ada crew yang cocok dengan filter.'}</EmptyState>
					) : (
						<div className="training-list">
							{filteredCrews.map((crew) => (
								<details className="training-crew" key={crew.id}>
									<summary className="training-crew-summary">
										<span className="training-crew-name">
											<strong>{crew.name}</strong>
											<span className="muted">{[crew.code, crew.category, crew.fleet, crew.position].filter(Boolean).join(' · ')}</span>
										</span>
										<StatusBar crew={crew} />
										<span className="training-counts">
											{crew.expired > 0 && <span className="badge badge-danger">{crew.expired} expired</span>}
											{crew.soon > 0 && <span className="badge badge-warning">{crew.soon} segera</span>}
											{crew.valid > 0 && <span className="badge badge-ok">{crew.valid} berlaku</span>}
										</span>
										<span className="training-chevron" aria-hidden="true">⌄</span>
									</summary>

									{crew.items.length === 0 ? (
										<p className="training-empty-detail">Belum ada detail training untuk crew ini.</p>
									) : (
										<div className="training-table-wrap">
											<table className="table training-table">
												<thead>
													<tr><th>Item training</th><th>Next check</th><th>Sisa hari</th><th>Status</th></tr>
												</thead>
												<tbody>
													{crew.items.map((item) => (
														<tr key={item.id}>
															<td>{item.name}</td>
															<td>{formatDate(item.next_check)}</td>
															<td className={`training-status-${item.status}`}>
																{item.days_left < 0 ? `Lewat ${Math.abs(item.days_left)} hari` : `${item.days_left} hari`}
															</td>
															<td><span className={`badge badge-${item.status}`}>{statusNames[item.status]}</span></td>
														</tr>
													))}
												</tbody>
											</table>
										</div>
									)}
								</details>
							))}
						</div>
					)}

					<p className="training-footnote">Status dihitung dari tanggal next check. “Segera” berarti jatuh tempo dalam 60 hari.</p>
				</>
			)}
		</section>
	);
}
