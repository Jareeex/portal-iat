import { useEffect, useState, type FormEvent } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Alert, Loader } from '../../components/common/Feedback';
import { trainingService } from '../../services/training.service';
import { errorMessage } from '../../utils/error';
import type { CrewFormData, GroupOption } from './training';

const emptyForm: CrewFormData = {
    group: '',
    pilot_role: '',
    name: '',
    email: '',
    code: '',
    position: '',
    rating: '',
    licence: '',
    dob: '',
    nationality: '',
    joined_iat: '',
    passport_no: '',
    passport_expiry: '',
    address: '',
    remarks: '',
};

export default function TrainingCrewFormPage({ crewId }: { crewId?: number }) {
    const navigate = useNavigate();
    const [form, setForm] = useState<CrewFormData>(emptyForm);
    const [groups, setGroups] = useState<GroupOption[]>([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let active = true;
        Promise.all([
            trainingService.index(),
            crewId ? trainingService.show(crewId) : Promise.resolve(null),
        ])
            .then(([index, detail]) => {
                if (!active) return;
                setGroups(index.groups);
                if (detail) {
                    if (!detail.can.manage) {
                        setError('Hanya master yang dapat mengubah data crew.');
                        return;
                    }
                    setForm({
                        ...emptyForm,
                        group: detail.crew.group,
                        pilot_role: detail.crew.pilot_role ?? '',
                        name: detail.crew.name,
                        email: detail.personal?.email ?? '',
                        code: detail.crew.code ?? '',
                        position: detail.crew.position ?? '',
                        rating: detail.personal?.rating ?? '',
                        licence: detail.personal?.licence ?? '',
                        dob: detail.personal?.dob ?? '',
                        nationality: detail.personal?.nationality ?? '',
                        joined_iat: detail.personal?.joined_iat ?? '',
                        passport_no: detail.personal?.passport_no ?? '',
                        passport_expiry: detail.personal?.passport_expiry ?? '',
                        address: detail.personal?.address ?? '',
                        remarks: detail.personal?.remarks ?? '',
                    });
                } else {
                    setForm({
                        ...emptyForm,
                        group: index.groups[0]?.key ?? '',
                    });
                }
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
    }, [crewId]);

    function setField(key: keyof CrewFormData, value: string): void {
        setForm((current) => ({ ...current, [key]: value }));
    }

    async function submit(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        setSaving(true);
        setError(null);
        try {
            if (crewId) {
                await trainingService.update(crewId, form);
                navigate(`/training/crews/${crewId}`);
            } else {
                const result = await trainingService.create(form);
                navigate(`/training/crews/${result.id}`);
            }
        } catch (cause) {
            setError(errorMessage(cause));
        } finally {
            setSaving(false);
        }
    }

    if (loading) return <Loader />;
    const field = (
        key: keyof CrewFormData,
        label: string,
        type = 'text',
        required = false,
    ) => (
        <label className="field training-form-field">
            <span className="field-label">{label}</span>
            <input
                type={type}
                value={form[key]}
                onChange={(event) => setField(key, event.target.value)}
                required={required}
            />
        </label>
    );

    return (
        <section className="training-page">
            <Link
                to={crewId ? `/training/crews/${crewId}` : '/training'}
                className="training-back-link"
            >
                ← Kembali
            </Link>
            <header className="page-head">
                <h2>{crewId ? 'Edit data crew' : 'Tambah crew'}</h2>
            </header>
            {error && <Alert>{error}</Alert>}
            <form
                className="panel training-form"
                onSubmit={(event) => void submit(event)}
            >
                <div className="training-form-grid">
                    <label className="field training-form-field">
                        <span className="field-label">Kelompok training</span>
                        <select
                            value={form.group}
                            disabled={Boolean(crewId)}
                            onChange={(event) =>
                                setField('group', event.target.value)
                            }
                            required
                        >
                            {groups.map((option) => (
                                <option key={option.key} value={option.key}>
                                    {option.category} · {option.label}
                                </option>
                            ))}
                        </select>
                    </label>
                    {groups.find((option) => option.key === form.group)
                        ?.category === 'Pilot' && (
                        <label className="field training-form-field">
                            <span className="field-label">
                                Role proficiency check
                            </span>
                            <select
                                value={form.pilot_role}
                                onChange={(event) =>
                                    setField(
                                        'pilot_role',
                                        event.target
                                            .value as CrewFormData['pilot_role'],
                                    )
                                }
                                required
                            >
                                <option value="">Pilih PIC atau SIC</option>
                                <option value="PIC">PIC</option>
                                <option value="SIC">SIC</option>
                            </select>
                        </label>
                    )}
                    {field('name', 'Nama lengkap')}
                    {field('email', 'Email untuk login', 'email', !crewId)}
                    {!crewId && (
                        <p className="muted training-form-note">
                            Akun dibuat belum aktif. Master perlu mengatur
                            password dan mengaktifkan akun di menu User.
                        </p>
                    )}
                    {field('code', 'Kode crew')}
                    {field('position', 'Posisi')}
                    {field('rating', 'Rating')}
                    {field('licence', 'Nomor lisensi')}
                    {field('dob', 'Tanggal lahir', 'date')}
                    {field('nationality', 'Kewarganegaraan')}
                    {field('joined_iat', 'Bergabung di IAT', 'date')}
                    {field('passport_no', 'Nomor paspor')}
                    {field('passport_expiry', 'Paspor berlaku sampai', 'date')}
                    {field('address', 'Alamat dan telepon')}
                    {field('remarks', 'Catatan')}
                </div>
                <div className="modal-actions">
                    <Link
                        to={crewId ? `/training/crews/${crewId}` : '/training'}
                        className="btn btn-ghost"
                    >
                        Batal
                    </Link>
                    <button
                        type="submit"
                        className="btn btn-primary"
                        disabled={saving}
                    >
                        {saving ? 'Menyimpan...' : 'Simpan'}
                    </button>
                </div>
            </form>
        </section>
    );
}
