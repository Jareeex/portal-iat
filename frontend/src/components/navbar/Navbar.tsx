import { useCallback, useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { notificationService } from '../../services/notification.service';
import type { NotificationInbox, UserNotification } from '../../services/notification.service';
import { errorMessage } from '../../utils/error';

export function Navbar({ onMenu }: { onMenu: () => void }) {
    const { user, logout } = useAuth();
    const navigate = useNavigate();
    const [inbox, setInbox] = useState<NotificationInbox>({
        notifications: [],
        unread_count: 0,
    });
    const [notificationsOpen, setNotificationsOpen] = useState(false);
    const [notificationError, setNotificationError] = useState<string | null>(null);

    const loadNotifications = useCallback(async () => {
        if (!user) return;
        try {
            setInbox(await notificationService.index());
            setNotificationError(null);
        } catch (cause) {
            setNotificationError(errorMessage(cause));
        }
    }, [user]);

    useEffect(() => {
        void loadNotifications();
        const interval = window.setInterval(() => {
            if (document.visibilityState === 'visible') void loadNotifications();
        }, 60_000);

        return () => window.clearInterval(interval);
    }, [loadNotifications]);

    async function openNotification(notification: UserNotification): Promise<void> {
        try {
            if (!notification.read_at) {
                await notificationService.markAsRead(notification.id);
            }
            setNotificationsOpen(false);
            await loadNotifications();
            if (notification.data.url) navigate(notification.data.url);
        } catch (cause) {
            setNotificationError(errorMessage(cause));
        }
    }

    async function markAllNotificationsAsRead(): Promise<void> {
        try {
            await notificationService.markAllAsRead();
            await loadNotifications();
        } catch (cause) {
            setNotificationError(errorMessage(cause));
        }
    }

    return (
        <header className="navbar">
            <button
                type="button"
                className="icon-btn menu-btn"
                onClick={onMenu}
                aria-label="Buka menu"
            >
                ☰
            </button>

            <div className="navbar-context">
                <span className="navbar-context-mark" aria-hidden="true" />
                <div>
                    <span>INDONESIA AIR TRANSPORT</span>
                    <strong>INTERNAL OPERATIONS</strong>
                </div>
            </div>

            <div className="navbar-spacer" />

            {user && (
                <div className="navbar-user">
                    <div className="notification-menu">
                        <button
                            type="button"
                            className="icon-btn notification-toggle"
                            aria-label={`Notifikasi, ${inbox.unread_count} belum dibaca`}
                            aria-expanded={notificationsOpen}
                            onClick={() => setNotificationsOpen((open) => !open)}
                        >
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" />
                                <path d="M10 21h4" />
                            </svg>
                            {inbox.unread_count > 0 && (
                                <span className="notification-badge">
                                    {inbox.unread_count > 99
                                        ? '99+'
                                        : inbox.unread_count}
                                </span>
                            )}
                        </button>
                        {notificationsOpen && (
                            <section
                                className="notification-panel"
                                aria-label="Notifikasi akun"
                            >
                                <header>
                                    <strong>Notifikasi</strong>
                                    {inbox.unread_count > 0 && (
                                        <button
                                            type="button"
                                            className="notification-read-all"
                                            onClick={() =>
                                                void markAllNotificationsAsRead()
                                            }
                                        >
                                            Tandai semua dibaca
                                        </button>
                                    )}
                                </header>
                                {notificationError && (
                                    <p className="notification-error" role="alert">
                                        Notifikasi gagal dimuat: {notificationError}
                                    </p>
                                )}
                                {inbox.notifications.length === 0 ? (
                                    <p className="notification-empty">
                                        Belum ada notifikasi.
                                    </p>
                                ) : (
                                    <ul>
                                        {inbox.notifications.map((notification) => (
                                            <li key={notification.id}>
                                                <button
                                                    type="button"
                                                    className={
                                                        notification.read_at
                                                            ? 'notification-item'
                                                            : 'notification-item notification-unread'
                                                    }
                                                    onClick={() =>
                                                        void openNotification(
                                                            notification,
                                                        )
                                                    }
                                                >
                                                    <strong>
                                                        {notification.data.crew_name ??
                                                            'Training'}
                                                    </strong>
                                                    <span>
                                                        {notification.data.trainings
                                                            ? `${notification.data.trainings.length} training perlu diperhatikan`
                                                            : `${notification.data.training_name ?? 'Pengingat training'} ${notification.data.reminder_label ?? ''}`}
                                                    </span>
                                                    {notification.data.trainings?.map(
                                                        (training) => (
                                                            <small
                                                                key={`${training.training_name}-${training.due_date}`}
                                                            >
                                                                {training.training_name}:{' '}
                                                                {training.reminder_label} (
                                                                {training.due_date})
                                                            </small>
                                                        ),
                                                    )}
                                                    {!notification.data.trainings &&
                                                        notification.data.due_date && (
                                                        <small>
                                                            Kedaluwarsa:{' '}
                                                            {notification.data.due_date}
                                                        </small>
                                                    )}
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </section>
                        )}
                    </div>
                    <span className="user-avatar" aria-hidden="true">
                        {user.name.trim().charAt(0).toUpperCase()}
                    </span>
                    <div className="navbar-user-text">
                        <strong>{user.name}</strong>
                        <span className="muted">
                            {user.is_master
                                ? 'Master'
                                : (user.department?.code ?? 'User')}
                            {user.full_access && !user.is_master
                                ? ' · Full access'
                                : ''}
                        </span>
                    </div>
                    <Link to="/profile" className="btn btn-ghost">
                        Profil
                    </Link>
                    <button
                        type="button"
                        className="btn btn-ghost"
                        onClick={() => void logout()}
                    >
                        Logout
                    </button>
                </div>
            )}
        </header>
    );
}
