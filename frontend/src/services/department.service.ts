import api from './api';
import type { Department } from '../types';

export interface DepartmentPayload {
  name: string;
  code: string;
  has_full_access: boolean;
}

export const departmentService = {
  list: () => api.get<Department[]>('/master/departments').then((r) => r.data),

  create: (payload: DepartmentPayload) =>
    api.post<Department>('/master/departments', payload).then((r) => r.data),

  update: (id: number, payload: DepartmentPayload) =>
    api.put<Department>(`/master/departments/${id}`, payload).then((r) => r.data),

  remove: (id: number) => api.delete(`/master/departments/${id}`),
};
