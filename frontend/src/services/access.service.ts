import api from './api';
import type { AccessResponse, PortalPage } from '../types';

export const accessService = {
  get: (userId: number) =>
    api.get<AccessResponse>(`/master/users/${userId}/access`).then((r) => r.data),

  /** Simpan akses TAMBAHAN (di luar departemen). */
  update: (userId: number, pageIds: number[]) =>
    api
      .put<{ user_id: number; pages: PortalPage[] }>(`/master/users/${userId}/access`, {
        page_ids: pageIds,
      })
      .then((r) => r.data),
};
