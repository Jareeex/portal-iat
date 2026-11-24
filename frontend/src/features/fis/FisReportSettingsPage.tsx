import { useEffect, useRef, useState, type FormEvent } from 'react';
import { Alert, Loader } from '../../components/common/Feedback';
import { Field } from '../../components/common/Field';
import {
    fisReportSettingsService,
    type FisReportAircraft,
} from '../../services/fis-report-settings.service';
import { errorMessage } from '../../utils/error';

export default function FisReportSettingsPage() {
    const [headerTitle, setHeaderTitle] = useState('');
    const [hasCustomLogo, setHasCustomLogo] = useState(false);
    const [aircraft, setAircraft] = useState<FisReportAircraft[]>([]);
    const [aircraftImages, setAircraftImages] = useState<
        Record<number, File | undefined>
    >({});
    const [savingAircraftId, setSavingAircraftId] = useState<number | null>(null);
    const [aircraftError, setAircraftError] = useState<string | null>(null);
    const [aircraftMessage, setAircraftMessage] = useState<string | null>(null);
    const [logoFile, setLogoFile] = useState<File | null>(null);
    const [removeLogo, setRemoveLogo] = useState(false);
    const [logoPreview, setLogoPreview] = useState<string | null>(null);
    const logoPreviewRef = useRef<string | null>(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [message, setMessage] = useState<string | null>(null);

    function replaceLogoPreview(url: string | null): void {
        if (logoPreviewRef.current) {
            URL.revokeObjectURL(logoPreviewRef.current);
        }
        logoPreviewRef.current = url;
        setLogoPreview(url);
    }

    async function loadSettings(): Promise<void> {
        const [settings, registeredAircraft] = await Promise.all([
            fisReportSettingsService.show(),
            fisReportSettingsService.aircraft(),
        ]);
        setHeaderTitle(settings.header_title);
        setHasCustomLogo(settings.has_custom_logo);
        setAircraft(registeredAircraft);

        if (settings.has_custom_logo) {
            replaceLogoPreview(
                URL.createObjectURL(await fisReportSettingsService.logo()),
            );
        } else {
            replaceLogoPreview(null);
        }
    }

    async function saveAircraftImage(item: FisReportAircraft): Promise<void> {
        const image = aircraftImages[item.id];
        if (!image) {
            setAircraftError(`Pilih gambar untuk pesawat ${item.registration}.`);
            return;
        }

        setSavingAircraftId(item.id);
        setAircraftError(null);
        setAircraftMessage(null);

        try {
            await fisReportSettingsService.updateAircraftImage(item.id, image);
            setAircraft((current) =>
                current.map((aircraftItem) =>
                    aircraftItem.id === item.id
                        ? { ...aircraftItem, has_report_image: true }
                        : aircraftItem,
                ),
            );
            setAircraftImages((current) => {
                const updated = { ...current };
                delete updated[item.id];
                return updated;
            });
            setAircraftMessage(
                `Gambar report pesawat ${item.registration} berhasil disimpan.`,
            );
        } catch (cause) {
            setAircraftError(errorMessage(cause));
        } finally {
            setSavingAircraftId(null);
        }
    }

    async function removeAircraftImage(item: FisReportAircraft): Promise<void> {
        if (
            !window.confirm(
                `Gunakan kembali gambar bawaan untuk pesawat ${item.registration}?`,
            )
        ) {
            return;
        }

        setSavingAircraftId(item.id);
        setAircraftError(null);
        setAircraftMessage(null);

        try {
            await fisReportSettingsService.removeAircraftImage(item.id);
            setAircraft((current) =>
                current.map((aircraftItem) =>
                    aircraftItem.id === item.id
                        ? { ...aircraftItem, has_report_image: false }
                        : aircraftItem,
                ),
            );
            setAircraftMessage(
                `Pesawat ${item.registration} kembali menggunakan gambar bawaan.`,
            );
        } catch (cause) {
            setAircraftError(errorMessage(cause));
        } finally {
            setSavingAircraftId(null);
        }
    }

    useEffect(() => {
        void loadSettings()
            .catch((cause: unknown) => setError(errorMessage(cause)))
            .finally(() => setLoading(false));

        return () => {
            if (logoPreviewRef.current) {
                URL.revokeObjectURL(logoPreviewRef.current);
            }
        };
    }, []);

    async function submit(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        setSaving(true);
        setError(null);
        setMessage(null);

        const payload = new FormData();
        payload.append('header_title', headerTitle);
        payload.append('remove_logo', removeLogo ? '1' : '0');
        if (logoFile) {
            payload.append('logo', logoFile);
        }

        try {
            const settings = await fisReportSettingsService.update(payload);
            setHeaderTitle(settings.header_title);
            setHasCustomLogo(settings.has_custom_logo);
            setLogoFile(null);
            setRemoveLogo(false);
            if (settings.has_custom_logo) {
                replaceLogoPreview(
                    URL.createObjectURL(await fisReportSettingsService.logo()),
                );
            } else {
                replaceLogoPreview(null);
            }
            setMessage('Header report FIS berhasil disimpan.');
        } catch (cause) {
            setError(errorMessage(cause));
        } finally {
            setSaving(false);
        }
    }

    return (
        <div>
            <div className="page-head">
                <h2>Header Report FIS</h2>
                <p className="muted">
                    Atur teks dan logo yang tampil pada PDF Flight Information Sheet.
                </p>
            </div>

            {error && <Alert>{error}</Alert>}
            {message && <Alert kind="success">{message}</Alert>}

            {loading ? (
                <Loader />
            ) : (
                <>
                    <form
                        className="panel fis-report-settings"
                        onSubmit={(event) => void submit(event)}
                    >
                        <Field label="Teks header PDF">
                            <textarea
                                className="input"
                                required
                                maxLength={150}
                                rows={3}
                                value={headerTitle}
                                onChange={(event) =>
                                    setHeaderTitle(event.target.value)
                                }
                            />
                        </Field>

                        <Field
                            label="Logo PDF"
                            hint="JPG, PNG, SVG, atau WebP hingga 2 MB. WebP dikonversi ke PNG; PNG, SVG, dan WebP mendukung transparansi."
                        >
                            <input
                                className="input"
                                type="file"
                                accept="image/png,image/jpeg,image/svg+xml,image/webp"
                                onChange={(event) => {
                                    setLogoFile(event.target.files?.[0] ?? null);
                                    setRemoveLogo(false);
                                }}
                            />
                        </Field>

                        {logoPreview ? (
                            <div className="fis-report-logo-preview">
                                <span className="field-label">Logo saat ini</span>
                                <img src={logoPreview} alt="Logo header report FIS" />
                            </div>
                        ) : (
                            <p className="muted">
                                {hasCustomLogo
                                    ? 'Logo kustom belum dapat dimuat.'
                                    : 'Logo bawaan IAT akan digunakan.'}
                            </p>
                        )}

                        <label className="check">
                            <input
                                type="checkbox"
                                checked={removeLogo}
                                disabled={!hasCustomLogo || logoFile !== null}
                                onChange={(event) =>
                                    setRemoveLogo(event.target.checked)
                                }
                            />
                            Kembali ke logo bawaan IAT
                        </label>

                        <button
                            type="submit"
                            className="btn btn-primary"
                            disabled={saving}
                        >
                            {saving ? 'Menyimpan...' : 'Simpan pengaturan'}
                        </button>
                    </form>

                    <section className="panel fis-aircraft-image-settings">
                        <h3>Gambar pesawat untuk report PDF</h3>
                        <p className="muted">
                            Gambar dipilih berdasarkan registrasi pesawat pada data
                            flight. Gunakan JPG, PNG, SVG, atau WebP hingga 5 MB.
                        </p>

                        {aircraftError && <Alert>{aircraftError}</Alert>}
                        {aircraftMessage && (
                            <Alert kind="success">{aircraftMessage}</Alert>
                        )}

                        {aircraft.length === 0 ? (
                            <p className="muted">
                                Belum ada pesawat yang terdaftar.
                            </p>
                        ) : (
                            <div className="fis-aircraft-image-list">
                                {aircraft.map((item) => (
                                    <div
                                        className="fis-aircraft-image-row"
                                        key={item.id}
                                    >
                                        <div>
                                            <strong>{item.registration}</strong>
                                            <div className="muted">
                                                {item.aircraft_type} ·{' '}
                                                {item.seat_capacity} kursi
                                            </div>
                                            <span className="muted">
                                                {item.has_report_image
                                                    ? 'Gambar khusus aktif'
                                                    : 'Menggunakan gambar bawaan'}
                                            </span>
                                        </div>
                                        <Field
                                            label="Gambar pesawat"
                                            hint="JPG/PNG/SVG/WebP, maksimal 5 MB."
                                        >
                                            <input
                                                className="input"
                                                type="file"
                                                accept="image/png,image/jpeg,image/svg+xml,image/webp"
                                                onChange={(event) =>
                                                    setAircraftImages((current) => ({
                                                        ...current,
                                                        [item.id]:
                                                            event.target.files?.[0],
                                                    }))
                                                }
                                            />
                                        </Field>
                                        <div className="row-actions">
                                            <button
                                                type="button"
                                                className="btn btn-primary"
                                                disabled={
                                                    savingAircraftId !== null ||
                                                    !aircraftImages[item.id]
                                                }
                                                onClick={() =>
                                                    void saveAircraftImage(item)
                                                }
                                            >
                                                {savingAircraftId === item.id
                                                    ? 'Menyimpan...'
                                                    : 'Simpan gambar'}
                                            </button>
                                            <button
                                                type="button"
                                                className="btn btn-ghost"
                                                disabled={
                                                    savingAircraftId !== null ||
                                                    !item.has_report_image
                                                }
                                                onClick={() =>
                                                    void removeAircraftImage(item)
                                                }
                                            >
                                                Gunakan bawaan
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </section>
                </>
            )}
        </div>
    );
}
