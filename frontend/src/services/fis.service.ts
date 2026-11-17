import api from './api';
import type { Paginated } from '../types';

export interface AircraftRegistration {
    id: number;
    registration: string;
    aircraft_type: string;
    seat_capacity: number;
}

export interface AirportOption {
    code: string;
    ident: string;
    iata_code: string | null;
    icao_code: string | null;
    name: string;
    keywords: string | null;
    municipality: string | null;
    country_name: string | null;
    iso_country: string | null;
    type: string;
    scheduled_service: boolean;
}

export interface FlightSchedule {
    date: string;
    from: string;
    to: string;
    etd: string;
    eta: string;
    duration_minutes?: number;
    remarks: string | null;
}

export interface FlightCrew {
    name: string;
    position: string;
    phone: string;
    remarks: string;
}

export interface FlightHandlingAgent {
    location: string;
    company: string;
    contact_person: string;
    phone: string;
}

export interface FlightPassenger {
    name: string;
    remarks: string;
}

export interface FlightAccommodation {
    location: string;
    hotel: string;
    address: string;
    phone: string;
}

export interface FlightPayload {
    flight_no: string;
    date: string;
    chartered_by: string;
    aircraft_registration_id: number;
    schedules: FlightSchedule[];
    crews: FlightCrew[];
    handling_agents: FlightHandlingAgent[];
    passengers: FlightPassenger[];
    accommodations: FlightAccommodation[];
    other: Record<string, string>;
}

export interface Flight extends FlightPayload {
    id: number;
    aircraft_registration: AircraftRegistration;
    total_duration_minutes: number;
    created_at: string;
    updated_at: string;
}

export interface FlightFilters {
    q?: string;
    date_from?: string;
    date_to?: string;
    page?: number;
}

const endpoint = '/fis/flights';

export const fisService = {
    searchAirports: (q: string) =>
        api
            .get<AirportOption[]>('/fis/airports', { params: { q } })
            .then((response) => response.data),

    aircraft: () =>
        api
            .get<AircraftRegistration[]>('/fis/aircraft')
            .then((response) => response.data),

    list: (params: FlightFilters = {}) =>
        api
            .get<Paginated<Flight>>(endpoint, { params })
            .then((response) => response.data),

    create: (payload: FlightPayload) =>
        api.post<Flight>(endpoint, payload).then((response) => response.data),

    update: (id: number, payload: FlightPayload) =>
        api
            .put<Flight>(`${endpoint}/${id}`, payload)
            .then((response) => response.data),

    remove: (id: number) => api.delete(`${endpoint}/${id}`),

    report: (id: number) =>
        api
            .get<Blob>(`${endpoint}/${id}/report`, { responseType: 'blob' })
            .then((response) => response.data),
};
