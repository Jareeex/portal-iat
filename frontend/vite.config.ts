import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// Dev: semua request /api diteruskan ke Laravel (php artisan serve), jadi tidak perlu setting CORS.
export default defineConfig({
    plugins: [react()],
    build: {
        outDir: '../public',
        emptyOutDir: false,
    },
    server: {
        port: 5173,
        proxy: {
            '/api': {
                target: 'http://localhost:8000',
                changeOrigin: true,
            },
        },
    },
});
