import api from './api';
import type { Paginated } from '../types';

export interface ItAssetCategory {
    id: number;
    code: number;
    name: string;
    assets_count?: number;
}

export interface ItAsset {
    id: number;
    category: Pick<ItAssetCategory, 'id' | 'code' | 'name'>;
    brand: string;
    serial_number: string | null;
    holder: string | null;
    division: string | null;
    condition: string | null;
    notes: string | null;
    status: string;
}

export interface ItAssetFilters {
    q?: string;
    category?: number | '';
    status?: string;
    condition?: string;
    division?: string;
    page?: number;
}

export interface ItAssetPayload {
    asset_category_id: number;
    brand: string;
    serial_number: string | null;
    user_name: string | null;
    division: string | null;
    condition: string | null;
    notes: string | null;
    status: string;
}

export interface ItAssetSummary {
    rows: Array<{ code: number; name: string; total: number }>;
    total: number;
}

const endpoint = '/it/assets';

export const itAssetService = {
    list: (params: ItAssetFilters = {}) =>
        api
            .get<Paginated<ItAsset>>(endpoint, { params })
            .then((response) => response.data),

    categories: () =>
        api
            .get<ItAssetCategory[]>(`${endpoint}/categories`)
            .then((response) => response.data),

    summary: () =>
        api
            .get<ItAssetSummary>(`${endpoint}/summary`)
            .then((response) => response.data),

    create: (payload: ItAssetPayload) =>
        api
            .post<ItAsset>(endpoint, payload)
            .then((response) => response.data),

    update: (id: number, payload: ItAssetPayload) =>
        api
            .put<ItAsset>(`${endpoint}/${id}`, payload)
            .then((response) => response.data),

    remove: (id: number) => api.delete(`${endpoint}/${id}`),

    createCategory: (payload: Pick<ItAssetCategory, 'code' | 'name'>) =>
        api
            .post<ItAssetCategory>(`${endpoint}/categories`, payload)
            .then((response) => response.data),

    updateCategory: (
        id: number,
        payload: Pick<ItAssetCategory, 'code' | 'name'>,
    ) =>
        api
            .put<ItAssetCategory>(`${endpoint}/categories/${id}`, payload)
            .then((response) => response.data),

    removeCategory: (id: number) =>
        api.delete(`${endpoint}/categories/${id}`),

    exportPdf: (params: Omit<ItAssetFilters, 'page'> = {}) =>
        api
            .get<Blob>(`${endpoint}/export`, {
                params,
                responseType: 'blob',
            })
            .then((response) => response.data),
};
