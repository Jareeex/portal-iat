# Portal frontend (React + TypeScript + Vite)

```bash
npm install
npm run dev        # http://localhost:5173
```

Backend Laravel harus jalan di `http://localhost:8000` (`php artisan serve`).
Request `/api/*` otomatis diteruskan lewat proxy Vite (lihat `vite.config.ts`), jadi tidak perlu setting CORS.

Login awal: `master@portal.test` / `ChangeMe123!`

## Menambah fitur baru

1. Master Panel -> Pages -> tambah halaman (key, route, departemen atau shared).
2. Buat komponen di `src/features/<nama>/`.
3. Daftarkan di `src/features/portal/registry.tsx` dengan key yang sama.
4. Di backend, kunci endpoint-nya: `->middleware('page.access:<key>')`.
