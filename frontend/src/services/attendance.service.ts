import api from './api';
import type { AttendanceIndex, AttendanceRecord, Paginated } from '../types';

export interface AttendanceListParams {
    date: string;
    q?: string;
    page?: number;
}

export const attendanceService = {
    list: (params: AttendanceListParams) =>
        api
            .get<AttendanceIndex>('/hr/attendance', { params })
            .then((response) => response.data),

    clockIn: (photo: File) => {
        const formData = new FormData();
        formData.append('photo', photo);

        return api
            .post<{ data: AttendanceRecord }>('/hr/attendance/clock-in', formData)
            .then((response) => response.data.data);
    },

    clockOut: (photo: File) => {
        const formData = new FormData();
        formData.append('photo', photo);

        return api
            .post<{ data: AttendanceRecord }>('/hr/attendance/clock-out', formData)
            .then((response) => response.data.data);
    },

    update: (
        id: number,
        payload: { clock_in_time: string; clock_out_time: string | null },
    ) =>
        api
            .put<{ data: AttendanceRecord }>(`/hr/attendance/${id}`, payload)
            .then((response) => response.data.data),

    photo: (id: number, type: 'clock-in' | 'clock-out') =>
        api
            .get<Blob>(`/hr/attendance/${id}/photo/${type}`, {
                responseType: 'blob',
            })
            .then((response) => response.data),
};
