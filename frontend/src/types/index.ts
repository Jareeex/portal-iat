export type Role = 'master' | 'user';

export interface DepartmentRef {
  id: number;
  name: string;
  code: string;
}

export interface Department extends DepartmentRef {
  has_full_access: boolean;
  users_count?: number;
  pages_count?: number;
}

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  role: Role;
  is_master: boolean;
  full_access: boolean;
  department: DepartmentRef | null;
}

/** Halaman yang boleh diakses user login (untuk sidebar). */
export interface PortalPage {
  id: number;
  department_id: number | null;
  key: string;
  name: string;
  description: string | null;
  route: string;
  icon: string | null;
}

export interface ManagedUser {
  id: number;
  name: string;
  email: string;
  role: Role;
  is_active: boolean;
  department_id: number | null;
  department?: (DepartmentRef & { has_full_access: boolean }) | null;
  pages_count?: number;
}

export interface ManagedPage extends PortalPage {
  sort_order: number;
  is_active: boolean;
  department?: DepartmentRef | null;
}

export interface Paginated<T> {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
}

export interface AccessPage {
  id: number;
  key: string;
  name: string;
  is_active: boolean;
  department: DepartmentRef | null;
  by_department: boolean;
  granted: boolean;
}

export interface AccessResponse {
  user_id: number;
  full_access: boolean;
  pages: AccessPage[];
}

export interface LoginResponse {
  token: string;
  user: AuthUser;
  pages: PortalPage[];
}
