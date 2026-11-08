import api from './api';
import type {
    EmployeeLinkOption,
    EmployeeProfile,
    JobPosition,
    Paginated,
} from '../types';

export interface EmployeeListParams {
    q?: string;
    department_id?: number | '';
    employment_status?: string;
    page?: number;
}

export interface EmployeeProfilePayload {
    name?: string;
    employee_number: string | null;
    job_title: string | null;
    office: string | null;
    employment_status: string | null;
    join_date: string | null;
    end_date: string | null;
}

export interface NewEmployeePayload extends EmployeeProfilePayload {
    name: string;
}

export interface JobPositionPayload {
    name: string;
    sort_order: number;
    is_active: boolean;
    is_leave_manager: boolean;
}

export const employeeService = {
    list: (params: EmployeeListParams = {}) =>
        api
            .get<Paginated<EmployeeProfile>>('/hr/employees', { params })
            .then((response) => response.data),

    unlinked: () =>
        api
            .get<EmployeeLinkOption[]>('/hr/employees/unlinked')
            .then((response) => response.data),

    create: (payload: NewEmployeePayload) =>
        api
            .post<EmployeeProfile>('/hr/employees', payload)
            .then((response) => response.data),

    update: (id: number, payload: EmployeeProfilePayload) =>
        api
            .put<EmployeeProfile>(`/hr/employees/${id}`, payload)
            .then((response) => response.data),

    jobPositions: () =>
        api
            .get<JobPosition[]>('/hr/job-positions')
            .then((response) => response.data),

    createJobPosition: (payload: JobPositionPayload) =>
        api
            .post<JobPosition>('/hr/job-positions', payload)
            .then((response) => response.data),

    updateJobPosition: (id: number, payload: JobPositionPayload) =>
        api
            .put<JobPosition>(`/hr/job-positions/${id}`, payload)
            .then((response) => response.data),
};
