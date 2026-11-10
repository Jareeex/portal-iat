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
    photo?: File | null;
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

function employeeFormData(payload: EmployeeProfilePayload): FormData | null {
    if (!payload.photo) return null;

    const formData = new FormData();
    Object.entries(payload).forEach(([key, value]) => {
        if (value === null) {
            formData.append(key, '');
        } else {
            formData.append(key, value);
        }
    });

    return formData;
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

    create: (payload: NewEmployeePayload) => {
        const formData = employeeFormData(payload);

        return api
            .post<EmployeeProfile>('/hr/employees', formData ?? payload)
            .then((response) => response.data);
    },

    update: (id: number, payload: EmployeeProfilePayload) => {
        const formData = employeeFormData(payload);
        if (formData) {
            formData.append('_method', 'PUT');
        }

        return api
            .request<EmployeeProfile>({
                method: formData ? 'POST' : 'PUT',
                url: `/hr/employees/${id}`,
                data: formData ?? payload,
            })
            .then((response) => response.data);
    },

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
