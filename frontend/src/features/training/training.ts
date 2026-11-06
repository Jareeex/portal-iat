export type Status = 'expired' | 'soon' | 'valid';
export type Category = 'Pilot' | 'Flight Attendant' | 'FOO & HLO';

export interface TrainingItem {
  id: number;
  name: string;
  next_check: string; // YYYY-MM-DD
  days_left: number;
  status: Status;
}

export interface CrewRecord {
  id: number;
  name: string;
  code: string | null;
  category: Category;
  fleet: string;
  position: string;
  expired: number;
  soon: number;
  valid: number;
  items: TrainingItem[];
}

export interface Summary {
  crew: number;
  expired: number;
  soon: number;
  valid: number;
}

export interface TrainingResponse {
  crews: CrewRecord[];
  summary: Summary;
  period: string; // e.g. "November 2026"
}
