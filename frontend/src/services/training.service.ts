import api from './api';
import type {
  CrewFormData,
  ImportResult,
  TrainingDetailResponse,
  TrainingIndexResponse,
} from '../features/training/training';

export const trainingService = {
  index: () => api.get<TrainingIndexResponse>('/training').then((response) => response.data),
  show: (crewId: number) => api.get<TrainingDetailResponse>(`/training/crews/${crewId}`).then((response) => response.data),
  create: (crew: CrewFormData) => api.post<{ id: number }>('/training/crews', crew).then((response) => response.data),
  update: (crewId: number, crew: Omit<CrewFormData, 'group'>) =>
    api.put<{ id: number }>(`/training/crews/${crewId}`, crew).then((response) => response.data),
  remove: (crewId: number) => api.delete(`/training/crews/${crewId}`),
  saveRecord: (crewId: number, typeId: number, dates: { last_check: string; next_check: string; plan_check: string }) =>
    api.put<{ message: string }>(`/training/crews/${crewId}/records/${typeId}`, dates).then((response) => response.data),
  exportCsv: (group?: string) =>
    api.get<Blob>('/training/export', { params: group ? { group } : {}, responseType: 'blob' }).then((response) => response.data),
  importCsv: (file: File) => {
    const formData = new FormData();
    formData.append('file', file);
    return api.post<ImportResult>('/training/import', formData).then((response) => response.data);
  },
};