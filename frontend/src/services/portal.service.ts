import api from './api';
import type { PortalPage } from '../types';

export const portalService = {
  dashboard: () =>
    api.get<{
      greeting: string;
      pages: PortalPage[];
      expired_trainings: { name: string; next_check: string }[];
    }>('/dashboard').then((r) => r.data),

  /** Contoh fitur yang dikunci per halaman (page.access:reports di backend). */
  reports: () =>
    api.get<{ data: unknown[]; message: string }>('/reports').then((r) => r.data),
};
