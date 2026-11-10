import api from './api';
import { BASE, type ProgramPageProps } from '../training-program/program';

export interface ProgramMessage {
    message: string;
}

export const trainingProgramService = {
    index: (year: number) =>
        api
            .get<ProgramPageProps>(BASE, { params: { year } })
            .then((response) => response.data),
    generatePlan: (year: number) =>
        api
            .post<ProgramMessage>(`${BASE}/plan/generate`, { year })
            .then((response) => response.data),
    storePlan: (
        programId: number,
        data: { year: number; month: number; crew_id: number },
    ) =>
        api
            .post<ProgramMessage>(
                `${BASE}/${programId}/plans`,
                data,
            )
            .then((response) => response.data),
    updatePlan: (planId: number, month: number) =>
        api
            .put<ProgramMessage>(`${BASE}/plans/${planId}`, {
                month,
            })
            .then((response) => response.data),
    removePlan: (planId: number) =>
        api
            .delete<ProgramMessage>(`${BASE}/plans/${planId}`)
            .then((response) => response.data),
    saveActual: (
        programId: number,
        year: number,
        month: number,
        data: {
            held_on: string;
            end_on: string;
            venue: string;
            notes: string;
            crew_ids: number[];
            update_records: boolean;
        },
    ) =>
        api
            .put<ProgramMessage>(
                `${BASE}/${programId}/actuals/${year}/${month}`,
                data,
            )
            .then((response) => response.data),
    removeActual: (actualId: number) =>
        api
            .delete<ProgramMessage>(`${BASE}/actuals/${actualId}`)
            .then((response) => response.data),
    storeProgram: (data: {
        name: string;
        category: string;
        type_ids: number[];
    }) =>
        api
            .post<ProgramMessage>(BASE, data)
            .then((response) => response.data),
    updateProgram: (
        programId: number,
        data: { name: string; category: string; type_ids: number[] },
    ) =>
        api
            .put<ProgramMessage>(`${BASE}/${programId}`, data)
            .then((response) => response.data),
    removeProgram: (programId: number) =>
        api
            .delete<ProgramMessage>(`${BASE}/${programId}`)
            .then((response) => response.data),
};
