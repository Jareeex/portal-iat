import api from './api';
import type { TrainingResponse } from '../features/training/training';

export const trainingService = {
  index: () => api.get<TrainingResponse>('/training').then((response) => response.data),
};