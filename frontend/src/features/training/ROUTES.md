# Training API

Frontend memakai React Router dan memanggil API Laravel dengan bearer token.
Semua endpoint memerlukan autentikasi dan akses halaman `training`; operasi tulis
hanya tersedia untuk master.

| Method | URL                                         | Keterangan                |
| ------ | ------------------------------------------- | ------------------------- |
| GET    | `/api/training`                             | Ringkasan crew dan status |
| GET    | `/api/training/crews/{crew}`                | Detail crew dan record    |
| POST   | `/api/training/crews`                       | Tambah crew               |
| PUT    | `/api/training/crews/{crew}`                | Ubah profil crew          |
| DELETE | `/api/training/crews/{crew}`                | Hapus crew beserta record |
| PUT    | `/api/training/crews/{crew}/records/{type}` | Simpan tanggal record     |
| GET    | `/api/training/export`                      | Unduh CSV                 |
| POST   | `/api/training/import`                      | Impor CSV hasil export    |

Data sumber lokal berada di `database/seeders/data/training.json` dan sengaja
diabaikan Git karena berisi informasi pribadi crew. Jalankan `TrainingPortalSeeder`
untuk menyiapkan halaman dan mengimpor data tersebut.
