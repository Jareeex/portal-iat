import axios from 'axios';
import { clearToken, getToken } from '../utils/storage';

const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL ?? '/api',
    headers: { Accept: 'application/json' },
});

api.interceptors.request.use((config) => {
    const token = getToken();
    if (token) config.headers.Authorization = `Bearer ${token}`;
    return config;
});

let onUnauthorized: (() => void) | null = null;

/** AuthProvider mendaftar di sini supaya token kedaluwarsa otomatis melempar user ke login. */
export function setUnauthorizedHandler(handler: (() => void) | null): void {
    onUnauthorized = handler;
}

api.interceptors.response.use(
    (res) => res,
    (error) => {
        if (error.response?.status === 401 && getToken()) {
            clearToken();
            onUnauthorized?.();
        }
        return Promise.reject(error);
    },
);

export default api;
