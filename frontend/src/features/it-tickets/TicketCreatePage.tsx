import { useEffect, useState, type FormEvent } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Alert } from '../../components/common/Feedback';
import { Field } from '../../components/common/Field';
import { ticketService } from '../../services/ticket.service';
import { errorMessage } from '../../utils/error';
import { type TicketOptions, type TicketPriority } from '../../types/ticket';

const priorityHint: Record<TicketPriority, string> = {
    low: 'Tidak mengganggu pekerjaan',
    medium: 'Mengganggu sebagian pekerjaan',
    high: 'Pekerjaan terhenti',
    critical: 'Operasional atau banyak orang terdampak',
};

export default function TicketCreatePage() {
    const navigate = useNavigate();
    const [saving, setSaving] = useState(false);
    const [options, setOptions] = useState<TicketOptions | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        void ticketService
            .options()
            .then(setOptions)
            .catch((loadError: unknown) => setError(errorMessage(loadError)));
    }, []);

    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setSaving(true);
        setError(null);

        try {
            const formData = new FormData(event.currentTarget);
            const result = await ticketService.create(formData);
            navigate(`/it/tickets/${result.ticket.id}`, {
                state: { message: result.message },
            });
        } catch (submitError) {
            setError(errorMessage(submitError));
        } finally {
            setSaving(false);
        }
    }

    return (
        <section>
            <header className="page-head">
                <Link to="/it/tickets">← Kembali ke IT Tickets</Link>
                <h2>Laporkan kendala IT</h2>
                <p className="muted">
                    Laporan akan masuk ke antrean departemen IT. Jelaskan
                    kendala agar tim dapat membantu lebih cepat.
                </p>
            </header>

            {error && <Alert>{error}</Alert>}

            {options ? (
                <form className="panel ticket-form" onSubmit={submit}>
                    <Field label="Judul masalah">
                        <input
                            name="title"
                            required
                            maxLength={150}
                            placeholder="Contoh: Printer ruang operasional tidak bisa mencetak"
                        />
                    </Field>

                    <div className="form-row">
                        <Field label="Kategori">
                            <select
                                name="category"
                                required
                                defaultValue={
                                    Object.keys(options.categories)[0]
                                }
                            >
                                {Object.entries(options.categories).map(
                                    ([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ),
                                )}
                            </select>
                        </Field>
                        <Field
                            label="Prioritas"
                            hint="Pilih sesuai dampak ke pekerjaan."
                        >
                            <select
                                name="priority"
                                required
                                defaultValue="medium"
                            >
                                {(
                                    Object.keys(
                                        options.priorities,
                                    ) as TicketPriority[]
                                ).map((priority) => (
                                    <option key={priority} value={priority}>
                                        {options.priorities[priority]} —{' '}
                                        {priorityHint[priority]}
                                    </option>
                                ))}
                            </select>
                        </Field>
                    </div>

                    <Field
                        label="Detail masalah"
                        hint="Sertakan sejak kapan, pesan error, dan langkah yang sudah dicoba."
                    >
                        <textarea
                            name="description"
                            rows={5}
                            required
                            minLength={10}
                            maxLength={5000}
                        />
                    </Field>

                    <div className="form-row">
                        <Field label="Lokasi / unit / nomor aset (opsional)">
                            <input
                                name="location"
                                maxLength={150}
                                placeholder="Ruang Ops, PC-014"
                            />
                        </Field>
                        <Field label="Nomor HP / ekstensi (opsional)">
                            <input name="contact_phone" maxLength={30} />
                        </Field>
                    </div>

                    <Field
                        label="Lampiran (opsional)"
                        hint="Maksimal 3 file, 5 MB per file."
                    >
                        <input
                            name="files[]"
                            type="file"
                            multiple
                            accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.log,.doc,.docx,.xls,.xlsx"
                        />
                    </Field>

                    <div className="ticket-actions">
                        <Link className="btn btn-ghost" to="/it/tickets">
                            Batal
                        </Link>
                        <button
                            className="btn btn-primary"
                            type="submit"
                            disabled={saving}
                        >
                            {saving ? 'Mengirim...' : 'Kirim laporan'}
                        </button>
                    </div>
                </form>
            ) : !error ? (
                <div className="panel">
                    <p>Memuat formulir tiket...</p>
                </div>
            ) : null}
        </section>
    );
}
