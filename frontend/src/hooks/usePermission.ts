import { useMemo } from 'react';
import { useAuth } from './useAuth';

/** Cek hak akses di UI. Backend tetap jadi penjaga utama; ini hanya untuk menyembunyikan menu/tombol. */
export function usePermission() {
  const { user, pages } = useAuth();
  const keys = useMemo(() => new Set(pages.map((p) => p.key)), [pages]);

  return {
    isMaster: !!user?.is_master,
    fullAccess: !!user?.full_access,
    can: (pageKey: string) => keys.has(pageKey),
  };
}
