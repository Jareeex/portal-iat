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
    access_level?: 'view' | 'edit';
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

export interface EmployeeProfile {
    id: number;
    user_id: number | null;
    name: string;
    employee_number: string | null;
    job_title: string | null;
    office: string | null;
    employment_status: string | null;
    photo_url: string | null;
    join_date: string | null;
    end_date: string | null;
    user: {
        department: DepartmentRef | null;
    } | null;
}

export interface EmployeeLinkOption {
    id: number;
    name: string;
}

export interface AttendanceRecord {
    id: number;
    attendance_date: string;
    clock_in_at: string;
    clock_out_at: string | null;
    clock_in_photo_available: boolean;
    clock_out_photo_available: boolean;
    employee: {
        id: number;
        name: string;
        employee_number: string | null;
        department: string | null;
    };
}

export interface AttendanceIndex {
    today: AttendanceRecord | null;
    records: Paginated<AttendanceRecord>;
    timezone: string;
}

export interface JobPosition {
    id: number;
    name: string;
    sort_order: number;
    is_active: boolean;
    is_leave_manager: boolean;
}

export interface LeaveManagerOption {
    id: number;
    name: string;
    job_title: string;
}

export type LeaveStatus =
    | 'manager_pending'
    | 'hr_pending'
    | 'manager_rejected'
    | 'approved'
    | 'rejected'
    | 'cancelled';

export interface LeaveType {
    id: number;
    name: string;
    annual_quota_days: number | null;
    requires_attachment: boolean;
    is_paid: boolean;
}

export interface LeaveRequest {
    id: number;
    status: LeaveStatus;
    start_date: string;
    end_date: string;
    days: number;
    reason: string;
    contact_phone: string | null;
    attachment_url: string | null;
    leave_type: Pick<LeaveType, 'id' | 'name' | 'is_paid'>;
    employee: {
        id: number;
        user_id: number | null;
        employee_number: string | null;
        name: string | null;
        department: string | null;
    };
    manager: { id: number; name: string } | null;
    manager_reviewed_by: string | null;
    manager_reviewed_at: string | null;
    manager_review_note: string | null;
    reviewed_by: string | null;
    reviewed_at: string | null;
    review_note: string | null;
    created_at: string;
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
    permission: 'view' | 'edit' | null;
}

export interface AccessResponse {
    user_id: number;
    full_access: boolean;
    is_master: boolean;
    pages: AccessPage[];
}

export interface LoginResponse {
    token: string;
    user: AuthUser;
    pages: PortalPage[];
}
