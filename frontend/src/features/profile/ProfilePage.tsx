import { useState, type FormEvent } from 'react';
import { Alert } from '../../components/common/Feedback';
import { Field } from '../../components/common/Field';
import { useAuth } from '../../hooks/useAuth';
import { authService } from '../../services/auth.service';
import { errorMessage } from '../../utils/error';

interface PasswordForm {
    current_password: string;
    password: string;
    password_confirmation: string;
}

const emptyPasswordForm: PasswordForm = {
    current_password: '',
    password: '',
    password_confirmation: '',
};

export default function ProfilePage() {
    const { user } = useAuth();
    const [form, setForm] = useState<PasswordForm>(emptyPasswordForm);
    const [error, setError] = useState<string | null>(null);
    const [saved, setSaved] = useState(false);
    const [saving, setSaving] = useState(false);

    async function onSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setError(null);
        setSaved(false);
        setSaving(true);

        try {
            await authService.changePassword(form);
            setForm(emptyPasswordForm);
            setSaved(true);
        } catch (cause) {
            setError(errorMessage(cause));
        } finally {
            setSaving(false);
        }
    }

    return (
        <div>
            <div className="page-head">
                <h2>Profil</h2>
                <p className="muted">
                    Informasi akun dan pengaturan keamanan Anda.
                </p>
            </div>

            <section className="panel profile-panel">
                <h3>Informasi akun</h3>
                <dl className="profile-details">
                    <div>
                        <dt>Nama</dt>
                        <dd>{user?.name}</dd>
                    </div>
                    <div>
                        <dt>Email</dt>
                        <dd>{user?.email}</dd>
                    </div>
                    <div>
                        <dt>Departemen</dt>
                        <dd>{user?.department?.name ?? 'Tidak ada departemen'}</dd>
                    </div>
                </dl>
            </section>

            <section className="panel profile-panel">
                <h3>Ganti password</h3>
                <p className="muted">
                    Masukkan password saat ini untuk mengatur password baru.
                    Minimal 8 karakter; ketentuan keamanan tambahan dapat berlaku.
                </p>

                {error && <Alert>{error}</Alert>}
                {saved && (
                    <Alert kind="success">Password berhasil diperbarui.</Alert>
                )}

                <form onSubmit={onSubmit}>
                    <Field label="Password saat ini">
                        <input
                            type="password"
                            autoComplete="current-password"
                            value={form.current_password}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    current_password: event.target.value,
                                })
                            }
                            required
                        />
                    </Field>
                    <Field label="Password baru">
                        <input
                            type="password"
                            autoComplete="new-password"
                            minLength={8}
                            value={form.password}
                            onChange={(event) =>
                                setForm({ ...form, password: event.target.value })
                            }
                            required
                        />
                    </Field>
                    <Field label="Konfirmasi password baru">
                        <input
                            type="password"
                            autoComplete="new-password"
                            minLength={8}
                            value={form.password_confirmation}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    password_confirmation: event.target.value,
                                })
                            }
                            required
                        />
                    </Field>
                    <button
                        type="submit"
                        className="btn btn-primary"
                        disabled={saving}
                    >
                        {saving ? 'Menyimpan...' : 'Simpan password baru'}
                    </button>
                </form>
            </section>
        </div>
    );
}
