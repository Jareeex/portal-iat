import api from './api';
import type { AuthUser, LoginResponse, PortalPage } from '../types';

export const authService = {
    login: (email: string, password: string) =>
        api
            .post<LoginResponse>('/auth/login', {
                email,
                password,
                device_name: 'web',
            })
            .then((r) => r.data),

    me: () =>
        api
            .get<{ user: AuthUser; pages: PortalPage[] }>('/auth/me')
            .then((r) => r.data),

    logout: () => api.post('/auth/logout'),
};
