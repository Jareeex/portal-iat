import api from './api';
import type {
    LeaveManagerOption,
    LeaveRequest,
    LeaveType,
    Paginated,
} from '../types';

interface ResourcePagination<T> {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
    };
}

export interface LeaveInboxParams {
    page?: number;
    status?: string;
    q?: string;
}

export const leaveService = {
    types: () =>
        api.get<LeaveType[]>('/hr/leave-types').then((response) => response.data),

    managers: () =>
        api
            .get<LeaveManagerOption[]>('/hr/leave-managers')
            .then((response) => response.data),

    submit: (payload: FormData) =>
        api
            .post<{ message: string }>('/hr/leave-requests', payload)
            .then((response) => response.data),

    managerInbox: () =>
        api
            .get<ResourcePagination<LeaveRequest>>(
                '/hr/leave-requests/manager-inbox',
            )
            .then(({ data }) => ({ ...data.meta, data: data.data })),

    inbox: (params: LeaveInboxParams = {}) =>
        api
            .get<ResourcePagination<LeaveRequest>>(
                '/hr/leave-requests/inbox',
                { params },
            )
            .then(({ data }) => ({ ...data.meta, data: data.data })),

    pendingCount: () =>
        api
            .get<{ pending_count: number }>('/hr/leave-requests')
            .then((response) => response.data.pending_count),

    review: (
        id: number,
        decision: 'approve' | 'reject',
        reviewNote: string,
        managerReview = false,
    ) =>
        api
            .post<{ data: LeaveRequest }>(
                `/hr/leave-requests/${id}/${managerReview ? 'manager-' : ''}${decision}`,
                { review_note: reviewNote },
            )
            .then((response) => response.data.data),

    update: (
        id: number,
        payload: {
            leave_type_id: number;
            start_date: string;
            end_date: string;
            reason: string;
            contact_phone: string | null;
        },
    ) =>
        api
            .put<{ data: LeaveRequest }>(`/hr/leave-requests/${id}`, payload)
            .then((response) => response.data.data),

    attachment: (id: number) =>
        api
            .get<Blob>(`/hr/leave-requests/${id}/attachment`, {
                responseType: 'blob',
            })
            .then((response) => response.data),
};
