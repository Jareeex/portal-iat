import { useEffect, useState } from 'react';
import { fisService, type AirportOption } from '../../services/fis.service';
import { errorMessage } from '../../utils/error';

function airportLabel(airport: AirportOption): string {
    const codes = [airport.iata_code, airport.icao_code]
        .filter((code): code is string => Boolean(code))
        .join(' / ');
    const location = [airport.municipality, airport.country_name]
        .filter((part): part is string => Boolean(part))
        .join(', ');

    return [
        airport.name,
        location,
        codes || airport.ident,
    ].filter(Boolean).join(' — ');
}

export function AirportPicker({
    value,
    onChange,
}: {
    value: string;
    onChange: (value: string) => void;
}) {
    const [query, setQuery] = useState(value);
    const [options, setOptions] = useState<AirportOption[]>([]);
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!value) return;

        let cancelled = false;
        fisService
            .searchAirports(value)
            .then((airports) => {
                if (cancelled) return;
                const selected = airports.find(
                    (airport) =>
                        airport.code.toUpperCase() === value.toUpperCase() ||
                        airport.ident.toUpperCase() === value.toUpperCase() ||
                        airport.iata_code?.toUpperCase() === value.toUpperCase() ||
                        airport.icao_code?.toUpperCase() === value.toUpperCase(),
                );
                if (selected) setQuery(airportLabel(selected));
            })
            .catch((lookupError: unknown) => {
                if (!cancelled) setError(errorMessage(lookupError));
            });

        return () => {
            cancelled = true;
        };
    }, [value]);

    useEffect(() => {
        const term = query.trim();
        if (term.length < 2 || !open) {
            setOptions([]);
            setLoading(false);
            return;
        }

        let cancelled = false;
        const timer = window.setTimeout(() => {
            setLoading(true);
            setError(null);
            fisService
                .searchAirports(term)
                .then((airports) => {
                    if (!cancelled) setOptions(airports);
                })
                .catch((searchError: unknown) => {
                    if (!cancelled) {
                        setOptions([]);
                        setError(errorMessage(searchError));
                    }
                })
                .finally(() => {
                    if (!cancelled) setLoading(false);
                });
        }, 250);

        return () => {
            cancelled = true;
            window.clearTimeout(timer);
        };
    }, [open, query]);

    return (
        <div className="airport-picker">
            <input
                className="input"
                role="combobox"
                aria-autocomplete="list"
                aria-expanded={open && options.length > 0}
                aria-label="Cari bandara berdasarkan nama, kota, IATA, atau ICAO"
                placeholder="Ketik nama/kota atau kode IATA/ICAO"
                value={query}
                onFocus={() => setOpen(true)}
                onBlur={() => window.setTimeout(() => setOpen(false), 150)}
                onChange={(event) => {
                    setQuery(event.target.value);
                    setOpen(true);
                    setError(null);
                    onChange('');
                }}
            />
            {open && (
                <div className="airport-picker-options" role="listbox">
                    {loading && <div className="airport-picker-message">Mencari bandara...</div>}
                    {!loading && query.trim().length < 2 && (
                        <div className="airport-picker-message">
                            Ketik minimal 2 karakter untuk mencari.
                        </div>
                    )}
                    {!loading && query.trim().length >= 2 && options.length === 0 && !error && (
                        <div className="airport-picker-message">
                            Tidak ada bandara yang cocok.
                        </div>
                    )}
                    {error && <div className="airport-picker-error">{error}</div>}
                    {options.map((airport) => (
                        <button
                            type="button"
                            role="option"
                            aria-selected={airport.code === value}
                            key={airport.ident}
                            className="airport-picker-option"
                            onMouseDown={(event) => event.preventDefault()}
                            onClick={() => {
                                setQuery(airportLabel(airport));
                                setOpen(false);
                                onChange(airport.code);
                            }}
                        >
                            <strong>{airport.name}</strong>
                            <span>
                                {[airport.municipality, airport.country_name]
                                    .filter(Boolean)
                                    .join(', ')}
                            </span>
                            <small>
                                {[airport.iata_code, airport.icao_code]
                                    .filter(Boolean)
                                    .join(' / ') || airport.ident}
                            </small>
                            {airport.keywords && (
                                <small>Juga dikenal sebagai: {airport.keywords}</small>
                            )}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
