export const BASE = '/training/program';
export const MONTHS = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGUST', 'SEP', 'OKT', 'NOP', 'DES'];

export interface PlanRow {
  id: number; crew_id: number; name: string; month: number;
  due_date: string | null; overdue: boolean; done: boolean;
}

export interface ActualRow {
  id: number; month: number; held_on: string; end_on: string | null;
  venue: string | null; notes: string | null; crews: { id: number; name: string }[];
}

export interface ProgramRow {
  id: number; category: string; name: string; type_ids: number[]; plans: PlanRow[]; actuals: ActualRow[];
}

export interface CrewOpt { id: number; name: string; group_label: string }

export interface ProgramPageProps {
  year: number; years: number[]; now: { year: number; month: number };
  categories: Record<string, string>;
  programs: ProgramRow[];
  summary: { cells: number; cells_done: number; crew: number; crew_done: number };
  crews: CrewOpt[];
  trainingTypes: { id: number; label: string }[];
  can: { manage: boolean };
}

export const fmtDate = (d: string | null | undefined) =>
  d ? new Date(d + 'T00:00:00').toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '–';
export const fmtShort = (d: string | null | undefined) =>
  d ? new Date(d + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }) : '';
