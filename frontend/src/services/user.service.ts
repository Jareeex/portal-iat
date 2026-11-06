import api from './api';
import type { ManagedUser, Paginated, Role } from '../types';

export interface UserPayload {
  name: string;
  email: string;
  password?: string;
  role: Role;
  is_active: boolean;
  department_id: number | null;
}

export interface UserListParams {
  search?: string;
  department_id?: number | '';
  page?: number;
  per_page?: number;
}

export const userService = {
  list: (params: UserListParams = {}) =>
    api.get<Paginated<ManagedUser>>('/master/users', { params }).then((r) => r.data),

  create: (payload: UserPayload) =>
    api.post<ManagedUser>('/master/users', payload).then((r) => r.data),

  update: (id: number, payload: UserPayload) =>
    api.put<ManagedUser>(`/master/users/${id}`, payload).then((r) => r.data),

  remove: (id: number) => api.delete(`/master/users/${id}`),
};
