import api from './api';

export interface UserNotification {
    id: string;
    data: {
        crew_id?: number;
        crew_name?: string;
        trainings?: Array<{
            training_name: string;
            due_date: string;
            reminder_label: string;
            stage: string;
        }>;
        training_name?: string;
        due_date?: string;
        reminder_label?: string;
        url?: string;
    };
    read_at: string | null;
    created_at: string | null;
}

export interface NotificationInbox {
    notifications: UserNotification[];
    unread_count: number;
}

export const notificationService = {
    index: () =>
        api.get<NotificationInbox>('/notifications').then((response) => response.data),
    markAsRead: (id: string) =>
        api.post(`/notifications/${id}/read`).then((response) => response.data),
    markAllAsRead: () =>
        api.post('/notifications/read-all').then((response) => response.data),
};
