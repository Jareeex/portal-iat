import api from './api';
import type { EmployeeProfile, Paginated } from '../types';

export interface EmployeeListParams {
  q?: string;
  department_id?: number | '';
  employment_status?: string;
  page?: number;
}

export interface EmployeeProfilePayload {
  employee_number: string | null;
  job_title: string | null;
  office: string | null;
  employment_status: string | null;
  join_date: string | null;
  end_date: string | null;
}

export const employeeService = {
  list: (params: EmployeeListParams = {}) =>
    api.get<Paginated<EmployeeProfile>>('/hr/employees', { params }).then((response) => response.data),

  update: (id: number, payload: EmployeeProfilePayload) =>
    api.put<EmployeeProfile>(`/hr/employees/${id}`, payload).then((response) => response.data),
};
