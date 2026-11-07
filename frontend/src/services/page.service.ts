import api from './api';
import type { ManagedPage } from '../types';

export interface PagePayload {
    department_id: number | null;
    key: string;
    name: string;
    description: string | null;
    route: string;
    icon: string | null;
    sort_order: number;
    is_active: boolean;
}

export const pageService = {
    list: (departmentId?: number | '') =>
        api
            .get<ManagedPage[]>('/master/pages', {
                params: departmentId ? { department_id: departmentId } : {},
            })
            .then((r) => r.data),

    create: (payload: PagePayload) =>
        api.post<ManagedPage>('/master/pages', payload).then((r) => r.data),

    update: (id: number, payload: PagePayload) =>
        api
            .put<ManagedPage>(`/master/pages/${id}`, payload)
            .then((r) => r.data),

    remove: (id: number) => api.delete(`/master/pages/${id}`),
};
