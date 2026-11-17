<?php

namespace App\Modules\Hr\Support;

use App\Models\User;
use App\Modules\Hr\Models\AttendanceSetting;
use Illuminate\Validation\ValidationException;

class AttendanceGeofenceService
{
    /**
     * @return array{
     *     latitude: float,
     *     longitude: float,
     *     distance_meters: int,
     *     is_offsite: bool,
     *     reason: string|null,
     *     reason_details: string|null
     * }
     */
    public function evaluate(
        User $user,
        float $latitude,
        float $longitude,
        ?string $reason,
        ?string $reasonDetails,
    ): array {
        $settings = AttendanceSetting::query()->findOrFail(1);
        $distanceMeters = $this->distanceMeters(
            $latitude,
            $longitude,
            $settings->latitude,
            $settings->longitude,
        );
        $isOffsite = $distanceMeters > $settings->radius_meters;

        if ($isOffsite && ! $user->on_leave_enabled) {
            throw ValidationException::withMessages([
                'latitude' => 'Lokasi di luar radius kantor dan akses On Leave belum diaktifkan.',
            ]);
        }

        if ($isOffsite && $reason === null) {
            throw ValidationException::withMessages([
                'reason' => 'Alasan wajib diisi saat absensi di luar radius kantor.',
            ]);
        }

        if ($isOffsite && $reason === 'other' && trim($reasonDetails ?? '') === '') {
            throw ValidationException::withMessages([
                'reason_details' => 'Keterangan wajib diisi untuk alasan Lainnya.',
            ]);
        }

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'distance_meters' => (int) ceil($distanceMeters),
            'is_offsite' => $isOffsite,
            'reason' => $isOffsite ? $reason : null,
            'reason_details' => $isOffsite ? $reasonDetails : null,
        ];
    }

    private function distanceMeters(
        float $latitude,
        float $longitude,
        float $officeLatitude,
        float $officeLongitude,
    ): float {
        $latitudeDelta = deg2rad($officeLatitude - $latitude);
        $longitudeDelta = deg2rad($officeLongitude - $longitude);
        $haversine = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitude))
            * cos(deg2rad($officeLatitude))
            * sin($longitudeDelta / 2) ** 2;

        return 6_371_000 * 2 * asin(min(1, sqrt($haversine)));
    }
}
