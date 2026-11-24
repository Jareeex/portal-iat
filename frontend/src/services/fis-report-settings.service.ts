import api from './api';

export interface FisReportSettings {
    header_title: string;
    has_custom_logo: boolean;
}

export interface FisReportAircraft {
    id: number;
    registration: string;
    aircraft_type: string;
    seat_capacity: number;
    has_report_image: boolean;
}

export const fisReportSettingsService = {
    show: () =>
        api
            .get<FisReportSettings>('/master/fis-report-settings')
            .then((response) => response.data),

    logo: () =>
        api
            .get<Blob>('/master/fis-report-settings/logo', {
                responseType: 'blob',
            })
            .then((response) => response.data),

    update: (payload: FormData) =>
        api
            .post<FisReportSettings>('/master/fis-report-settings', payload)
            .then((response) => response.data),

    aircraft: () =>
        api
            .get<FisReportAircraft[]>('/master/fis-report-settings/aircraft')
            .then((response) => response.data),

    updateAircraftImage: (aircraftId: number, image: File) => {
        const payload = new FormData();
        payload.append('image', image);

        return api
            .post<{ id: number; has_report_image: boolean }>(
                `/master/fis-report-settings/aircraft/${aircraftId}/image`,
                payload,
            )
            .then((response) => response.data);
    },

    removeAircraftImage: (aircraftId: number) =>
        api
            .delete<{ id: number; has_report_image: boolean }>(
                `/master/fis-report-settings/aircraft/${aircraftId}/image`,
            )
            .then((response) => response.data),
};
