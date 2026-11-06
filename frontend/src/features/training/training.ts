export type Status = 'expired' | 'soon' | 'valid';

export interface CrewRecord {
  id: number;
  name: string;
  code: string | null;
  group: string;
  group_label: string;
  category: string;
  position: string | null;
  expired: number;
  soon: number;
  valid: number;
}

export interface GroupOption {
  key: string;
  label: string;
  category: string;
}

export interface TrainingSummary {
  crew: number;
  expired: number;
  soon: number;
  valid: number;
}

export interface TrainingIndexResponse {
  crews: CrewRecord[];
  summary: TrainingSummary;
  period: string;
  groups: GroupOption[];
  can: { manage: boolean };
}

export interface TrainingRecord {
  type_id: number;
  name: string;
  validity: string;
  last_check: string | null;
  next_check: string | null;
  plan_check: string | null;
  days_left: number | null;
  status: Status | null;
}

export interface TrainingSection {
  section: string;
  items: TrainingRecord[];
}

export interface TrainingCrewDetail {
  id: number;
  name: string;
  code: string | null;
  group: string;
  group_label: string;
  category: string;
  position: string | null;
}

export interface CrewPersonalData {
  dob: string | null;
  address: string | null;
  passport_no: string | null;
  passport_expiry: string | null;
  nationality: string | null;
  joined_iat: string | null;
  licence: string | null;
  rating: string | null;
  remarks: string | null;
}

export interface TrainingDetailResponse {
  crew: TrainingCrewDetail;
  personal: CrewPersonalData | null;
  sections: TrainingSection[];
  counts: Omit<TrainingSummary, 'crew'>;
  can: { manage: boolean };
}

export interface CrewFormData {
  group: string;
  name: string;
  code: string;
  position: string;
  rating: string;
  licence: string;
  dob: string;
  nationality: string;
  joined_iat: string;
  passport_no: string;
  passport_expiry: string;
  address: string;
  remarks: string;
}

export interface ImportResult {
  message: string;
  errors: string[];
}
