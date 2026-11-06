import type { ComponentType } from 'react';
import ReportsPage from '../reports/ReportsPage';
import TransactionsPage from '../transactions/TransactionsPage';
import TrainingPage from '../training/TrainingRootPage';

/**
 * Daftarkan komponen halaman asli di sini, key = `pages.key` di database.
 * Halaman yang belum terdaftar otomatis tampil sebagai placeholder.
 *
 * Contoh nambah fitur HR:
 *   1. Buat halaman di Master Panel -> Pages (key: hr-employees, route: /hr/employees, departemen HR)
 *   2. Buat komponen features/hr/EmployeesPage.tsx
 *   3. Tambah di bawah:  'hr-employees': EmployeesPage
 */
export const pageRegistry: Record<string, ComponentType> = {
  reports: ReportsPage,
  transactions: TransactionsPage,
  training: TrainingPage,
};
