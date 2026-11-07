import { useEffect, useState } from 'react';
import { portalService } from '../../services/portal.service';
import { errorMessage } from '../../utils/error';
import { Alert, Loader } from '../../components/common/Feedback';

/** Contoh halaman fitur: data diambil dari endpoint yang dikunci middleware page.access:reports. */
export default function ReportsPage() {
    const [message, setMessage] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        portalService
            .reports()
            .then((d) => setMessage(d.message))
            .catch((e) => setError(errorMessage(e)))
            .finally(() => setLoading(false));
    }, []);

    return (
        <div>
            <div className="page-head">
                <h2>Reports</h2>
            </div>
            {loading && <Loader />}
            {error && <Alert>{error}</Alert>}
            {message && <Alert kind="success">{message}</Alert>}
        </div>
    );
}
