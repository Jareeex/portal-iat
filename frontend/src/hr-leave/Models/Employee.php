<?php

namespace App\Modules\Hr\Models;

use App\Modules\Hr\Support\Hr;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class Employee extends Model
{
    use SoftDeletes;

    protected $table = 'employees';

    protected $guarded = ['id', 'user_id', 'photo_path'];

    protected $hidden = ['photo_path'];

    protected $appends = ['photo_url'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'join_date' => 'date',
            'end_date' => 'date',
            'ktp_issued_at' => 'date',
            'ktp_expired_at' => 'date',
            'passport_issued_at' => 'date',
            'passport_expired_at' => 'date',
            'weight_kg' => 'decimal:2',
            'height_cm' => 'decimal:2',
            // data sensitif disimpan terenkripsi
            'ktp_number' => 'encrypted',
            'passport_number' => 'encrypted',
            'npwp_number' => 'encrypted',
        ];
    }

    /* ---------- Relations ---------- */

    public function user(): BelongsTo
    {
        return $this->belongsTo(Hr::userModel());
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(EmployeeExperience::class)->orderByDesc('start_date');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class)->latest();
    }

    /* ---------- Helpers ---------- */

    /** Ambil (atau buat) profil employee untuk sebuah user. */
    public static function forUser($user): self
    {
        $employee = static::withTrashed()->firstWhere('user_id', $user->getKey());

        if ($employee) {
            if ($employee->trashed()) {
                $employee->restore();
            }

            return $employee;
        }

        return static::forceCreate(['user_id' => $user->getKey()]);
    }

    public function replacePhoto(UploadedFile $file): void
    {
        if ($this->photo_path) {
            Storage::disk(config('hr.photo_disk'))->delete($this->photo_path);
        }

        $this->photo_path = $file->store('hr/photos', config('hr.photo_disk'));
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path
            ? Storage::disk(config('hr.photo_disk'))->url($this->photo_path)
            : null;
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('employee_number', 'like', "%{$term}%")
                ->orWhere('job_title', 'like', "%{$term}%")
                ->orWhere('department', 'like', "%{$term}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%"));
        });
    }
}
