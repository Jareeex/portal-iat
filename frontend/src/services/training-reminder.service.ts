import api from './api';

export interface TrainingReminderRecipient {
    id: number;
    name: string;
    email: string;
    role: string;
    training_reminder_enabled: boolean;
}

export const trainingReminderService = {
    recipients: () =>
        api
            .get<TrainingReminderRecipient[]>(
                '/master/training-reminders/recipients',
            )
            .then((response) => response.data),
    setRecipientEnabled: (userId: number, enabled: boolean) =>
        api
            .put<{ id: number; training_reminder_enabled: boolean }>(
                `/master/training-reminders/recipients/${userId}`,
                { enabled },
            )
            .then((response) => response.data),
    sendAll: () =>
        api
            .post<{ message: string }>('/master/training-reminders/send-all')
            .then((response) => response.data),
};
