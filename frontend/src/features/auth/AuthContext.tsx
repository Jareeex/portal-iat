import { createContext } from 'react';
import type { AuthUser, PortalPage } from '../../types';

export interface AuthState {
    user: AuthUser | null;
    pages: PortalPage[];
    loading: boolean;
    login: (email: string, password: string) => Promise<void>;
    logout: () => Promise<void>;
    refresh: () => Promise<void>;
}

export const AuthContext = createContext<AuthState | null>(null);
