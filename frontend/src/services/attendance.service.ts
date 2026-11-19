import api from './api';
import type {
    AttendanceGeofenceSettings,
    AttendanceIndex,
    AttendanceRecord,
    AttendanceUserOption,
    Paginated,
} from '../types';

export interface AttendanceLocationPayload {
    latitude: number;
    longitude: number;
    reason?: string;
    reason_details?: string;
}

export interface AttendanceListParams {
    date: string;
    q?: string;
    page?: number;
}

export interface AttendanceReportFilters {
    from: string;
    to: string;
    name?: string;
}

export const attendanceService = {
    list: (params: AttendanceListParams) =>
        api
            .get<AttendanceIndex>('/hr/attendance', { params })
            .then((response) => response.data),

    report: (params: AttendanceReportFilters) =>
        api
            .get<{ data: AttendanceRecord[] }>('/hr/attendance/report', {
                params,
            })
            .then((response) => response.data.data),

    reportPdf: (params: AttendanceReportFilters) =>
        api
            .get<Blob>('/hr/attendance/report/pdf', {
                params,
                responseType: 'blob',
            })
            .then((response) => response.data),

    clockIn: (photo: File, location: AttendanceLocationPayload) => {
        const formData = new FormData();
        formData.append('photo', photo);
        formData.append('latitude', String(location.latitude));
        formData.append('longitude', String(location.longitude));
        if (location.reason) formData.append('reason', location.reason);
        if (location.reason_details) {
            formData.append('reason_details', location.reason_details);
        }

        return api
            .post<{ data: AttendanceRecord }>('/hr/attendance/clock-in', formData)
            .then((response) => response.data.data);
    },

    clockOut: (photo: File, location: AttendanceLocationPayload) => {
        const formData = new FormData();
        formData.append('photo', photo);
        formData.append('latitude', String(location.latitude));
        formData.append('longitude', String(location.longitude));
        if (location.reason) formData.append('reason', location.reason);
        if (location.reason_details) {
            formData.append('reason_details', location.reason_details);
        }

        return api
            .post<{ data: AttendanceRecord }>('/hr/attendance/clock-out', formData)
            .then((response) => response.data.data);
    },

    updateGeofenceRadius: (radius_meters: number) =>
        api
            .put<{ data: AttendanceGeofenceSettings }>(
                '/hr/attendance/settings',
                { radius_meters },
            )
            .then((response) => response.data.data),

    update: (
        id: number,
        payload: {
            user_id: number;
            attendance_date: string;
            clock_in_time: string;
            clock_out_time: string | null;
        },
    ) =>
        api
            .put<{ data: AttendanceRecord }>(`/hr/attendance/${id}`, payload)
            .then((response) => response.data.data),

    createManual: (payload: {
        user_id: number;
        attendance_date: string;
        clock_in_time: string;
        clock_out_time: string | null;
    }) =>
        api
            .post<{ data: AttendanceRecord }>('/hr/attendance', payload)
            .then((response) => response.data.data),

    remove: (id: number) =>
        api.delete<{ message: string }>(`/hr/attendance/${id}`),

    photo: (id: number, type: 'clock-in' | 'clock-out') =>
        api
            .get<Blob>(`/hr/attendance/${id}/photo/${type}`, {
                responseType: 'blob',
            })
            .then((response) => response.data),
};
