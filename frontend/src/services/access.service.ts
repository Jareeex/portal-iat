import api from './api';
import type { AccessResponse, PortalPage } from '../types';

export const accessService = {
  get: (userId: number) =>
    api.get<AccessResponse>(`/master/users/${userId}/access`).then((r) => r.data),

  update: (userId: number, pageIds: number[], editPageIds: number[]) =>
    api
      .put<{ user_id: number; pages: PortalPage[] }>(`/master/users/${userId}/access`, {
        page_ids: pageIds,
        edit_page_ids: editPageIds,
      })
      .then((r) => r.data),
};
