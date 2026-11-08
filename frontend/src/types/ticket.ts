export type TicketStatus =
    | 'open'
    | 'in_progress'
    | 'waiting'
    | 'resolved'
    | 'closed';
export type TicketPriority = 'low' | 'medium' | 'high' | 'critical';

export const TICKET_STATUS_LABEL: Record<TicketStatus, string> = {
    open: 'Baru',
    in_progress: 'Diproses',
    waiting: 'Menunggu user',
    resolved: 'Teratasi',
    closed: 'Ditutup',
};

export const TICKET_PRIORITY_LABEL: Record<TicketPriority, string> = {
    low: 'Rendah',
    medium: 'Sedang',
    high: 'Tinggi',
    critical: 'Kritis',
};

export interface TicketSummary {
    id: number;
    number: string;
    title: string;
    category: string;
    priority: TicketPriority;
    status: TicketStatus;
    requester: string | null;
    assignee: string | null;
    created_at: string;
    updated_at: string;
}

export interface TicketStats {
    open: number;
    in_progress: number;
    waiting: number;
    resolved: number;
    closed: number;
    unassigned: number;
    critical: number;
}

export interface TicketIndexResponse {
    data: TicketSummary[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    stats: TicketStats;
    filters: {
        status: string;
        priority: string | null;
        category: string | null;
        assignee: string | null;
        q: string;
    };
    categories: Record<string, string>;
    staff: { id: number; name: string }[];
    can: { it: boolean };
}

export interface TicketAttachment {
    id: number;
    name: string;
    size: number;
    url: string;
}

export interface TicketTimelineItem {
    id: number;
    type: 'comment' | 'internal' | 'event';
    body: string;
    user: string | null;
    created_at: string;
    attachments: TicketAttachment[];
}

export type TicketDetail = Omit<TicketSummary, 'requester' | 'assignee'> & {
    description: string;
    location: string | null;
    contact_phone: string | null;
    resolution: string | null;
    requester: { name: string; email: string };
    assignee: { id: number; name: string } | null;
    first_response_at: string | null;
    resolved_at: string | null;
    closed_at: string | null;
    attachments: TicketAttachment[];
};

export interface TicketDetailResponse {
    ticket: TicketDetail;
    timeline: TicketTimelineItem[];
    staff: { id: number; name: string }[];
    categories: Record<string, string>;
    can: { it: boolean; comment: boolean; close: boolean; reopen: boolean };
}

export interface TicketAlerts {
    unassigned_count: number;
    latest_ticket_id: number;
    new_ticket_count: number;
    new_tickets: {
        id: number;
        number: string;
        title: string;
        created_at: string;
    }[];
}

export interface TicketOptions {
    categories: Record<string, string>;
    priorities: Record<string, string>;
}

export function formatTicketDate(value: string | null | undefined): string {
    if (!value) return '—';

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
