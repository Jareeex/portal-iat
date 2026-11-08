import api from './api';
import type {
    TicketAlerts,
    TicketDetailResponse,
    TicketIndexResponse,
    TicketOptions,
    TicketPriority,
    TicketStatus,
} from '../types/ticket';

export interface TicketListParams {
    page?: number;
    status?: string;
    priority?: string;
    category?: string;
    assignee?: string;
    q?: string;
}

export interface TicketUpdatePayload {
    status: TicketStatus;
    priority: TicketPriority;
    category: string;
    assignee_id: number | null;
    resolution: string;
}

export const ticketService = {
    list: (params: TicketListParams = {}) =>
        api
            .get<TicketIndexResponse>('/it-tickets', { params })
            .then(({ data }) => data),

    alerts: (afterId?: number) =>
        api
            .get<TicketAlerts>('/it-tickets/alerts', {
                params:
                    afterId === undefined ? undefined : { after_id: afterId },
            })
            .then(({ data }) => data),

    options: () =>
        api.get<TicketOptions>('/it-tickets/options').then(({ data }) => data),

    create: (payload: FormData) =>
        api
            .post<{ message: string; ticket: { id: number } }>(
                '/it-tickets',
                payload,
            )
            .then(({ data }) => data),

    show: (id: number) =>
        api
            .get<TicketDetailResponse>(`/it-tickets/${id}`)
            .then(({ data }) => data),

    update: (id: number, payload: TicketUpdatePayload) =>
        api
            .put<{ message: string }>(`/it-tickets/${id}`, payload)
            .then(({ data }) => data),

    take: (id: number) =>
        api
            .post<{ message: string }>(`/it-tickets/${id}/take`)
            .then(({ data }) => data),

    comment: (id: number, payload: FormData) =>
        api
            .post<{ message: string }>(`/it-tickets/${id}/comments`, payload)
            .then(({ data }) => data),

    close: (id: number) =>
        api
            .post<{ message: string }>(`/it-tickets/${id}/close`)
            .then(({ data }) => data),

    reopen: (id: number) =>
        api
            .post<{ message: string }>(`/it-tickets/${id}/reopen`)
            .then(({ data }) => data),

    attachment: (id: number) =>
        api
            .get<Blob>(`/it-tickets/attachments/${id}`, {
                responseType: 'blob',
            })
            .then(({ data }) => data),
};
