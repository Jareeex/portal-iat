import api from './api';
import type {
    CrewFormData,
    ImportResult,
    TrainingDetailResponse,
    TrainingIndexResponse,
} from '../features/training/training';

export const trainingService = {
    index: () =>
        api
            .get<TrainingIndexResponse>('/training')
            .then((response) => response.data),
    show: (crewId: number) =>
        api
            .get<TrainingDetailResponse>(`/training/crews/${crewId}`)
            .then((response) => response.data),
    create: (crew: CrewFormData) =>
        api
            .post<{ id: number }>('/training/crews', crew)
            .then((response) => response.data),
    createAircraft: (name: string) =>
        api
            .post<{ id: number; key: string; label: string; category: string }>(
                '/training/aircraft',
                { name },
            )
            .then((response) => response.data),
    removeAircraft: (aircraftId: number) =>
        api.delete(`/training/aircraft/${aircraftId}`),
    addCrewAircraft: (crewId: number, aircraftId: number) =>
        api.post(`/training/crews/${crewId}/aircrafts`, {
            aircraft_id: aircraftId,
        }),
    removeCrewAircraft: (crewId: number, aircraftId: number) =>
        api.delete(`/training/crews/${crewId}/aircrafts/${aircraftId}`),
    update: (
        crewId: number,
        crew: Partial<CrewFormData> & Pick<CrewFormData, 'name' | 'group'>,
    ) =>
        api
            .put<{ id: number }>(`/training/crews/${crewId}`, crew)
            .then((response) => response.data),
    remove: (crewId: number) => api.delete(`/training/crews/${crewId}`),
    uploadLicenseDocuments: (crewId: number, files: File[]) => {
        const formData = new FormData();
        files.forEach((file) => formData.append('files[]', file));
        return api
            .post<{
                documents: {
                    id: number;
                    name: string;
                    uploaded_at: string | null;
                }[];
            }>(`/training/crews/${crewId}/licenses`, formData)
            .then((response) => response.data);
    },
    showLicenseDocument: (crewId: number, documentId: number) =>
        api
            .get<Blob>(`/training/crews/${crewId}/licenses/${documentId}`, {
                responseType: 'blob',
            })
            .then((response) => response.data),
    removeLicenseDocument: (crewId: number, documentId: number) =>
        api.delete(`/training/crews/${crewId}/licenses/${documentId}`),
    saveRecord: (
        crewId: number,
        typeId: number,
        dates: { last_check: string; next_check: string; plan_check: string },
    ) =>
        api
            .put<{ message: string }>(
                `/training/crews/${crewId}/records/${typeId}`,
                dates,
            )
            .then((response) => response.data),
    exportCsv: (group?: string) =>
        api
            .get<Blob>('/training/export', {
                params: group ? { group } : {},
                responseType: 'blob',
            })
            .then((response) => response.data),
    importCsv: (file: File) => {
        const formData = new FormData();
        formData.append('file', file);
        return api
            .post<ImportResult>('/training/import', formData)
            .then((response) => response.data);
    },
};
