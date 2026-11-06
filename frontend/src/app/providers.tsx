import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';
import { AuthContext, type AuthState } from '../features/auth/AuthContext';
import { authService } from '../services/auth.service';
import { setUnauthorizedHandler } from '../services/api';
import { clearToken, getToken, setToken } from '../utils/storage';
import type { AuthUser, PortalPage } from '../types';

export function AppProviders({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [pages, setPages] = useState<PortalPage[]>([]);
  const [loading, setLoading] = useState<boolean>(() => getToken() !== null);

  const reset = useCallback(() => {
    setUser(null);
    setPages([]);
  }, []);

  // Pulihkan sesi saat halaman di-reload.
  useEffect(() => {
    setUnauthorizedHandler(reset);

    if (!getToken()) {
      setLoading(false);
      return () => setUnauthorizedHandler(null);
    }

    authService
      .me()
      .then((data) => {
        setUser(data.user);
        setPages(data.pages);
      })
      .catch(() => {
        clearToken();
        reset();
      })
      .finally(() => setLoading(false));

    return () => setUnauthorizedHandler(null);
  }, [reset]);

  const login = useCallback(async (email: string, password: string) => {
    const data = await authService.login(email, password);
    setToken(data.token);
    setUser(data.user);
    setPages(data.pages);
  }, []);

  const logout = useCallback(async () => {
    try {
      await authService.logout();
    } catch {
      /* token mungkin sudah tidak valid, tetap logout di sisi client */
    }
    clearToken();
    reset();
  }, [reset]);

  const refresh = useCallback(async () => {
    const data = await authService.me();
    setUser(data.user);
    setPages(data.pages);
  }, []);

  const value = useMemo<AuthState>(
    () => ({ user, pages, loading, login, logout, refresh }),
    [user, pages, loading, login, logout, refresh],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}
