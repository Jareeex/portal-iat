import { useLocation, useNavigate } from 'react-router-dom';
import TrainingCrewDetailPage from './TrainingCrewDetailPage';
import TrainingCrewFormPage from './TrainingCrewFormPage';
import TrainingIndexPage from './TrainingIndexPage';

export default function TrainingRootPage() {
    const { pathname } = useLocation();
    const navigate = useNavigate();
    const path = pathname.replace(/\/+$/, '') || '/training';
    const editMatch = path.match(/^\/training\/crews\/(\d+)\/edit$/);
    const detailMatch = path.match(/^\/training\/crews\/(\d+)$/);

    if (path === '/training/crews/create') return <TrainingCrewFormPage />;
    if (editMatch)
        return <TrainingCrewFormPage crewId={Number(editMatch[1])} />;
    if (detailMatch) {
        return (
            <TrainingCrewDetailPage
                crewId={Number(detailMatch[1])}
                onDeleted={() => navigate('/training')}
            />
        );
    }

    return <TrainingIndexPage />;
}
