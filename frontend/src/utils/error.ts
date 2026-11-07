import axios from 'axios';

/** Ambil pesan error paling berguna dari response Laravel (validasi 422 atau message). */
export function errorMessage(err: unknown): string {
    if (axios.isAxiosError(err)) {
        const data = err.response?.data as
            | { message?: string; errors?: Record<string, string[]> }
            | undefined;

        const firstValidation = data?.errors
            ? Object.values(data.errors)[0]?.[0]
            : undefined;
        if (firstValidation) return firstValidation;
        if (data?.message) return data.message;
        if (!err.response)
            return 'Tidak bisa terhubung ke server. Pastikan backend jalan.';
    }
    return 'Terjadi kesalahan. Coba lagi.';
}
