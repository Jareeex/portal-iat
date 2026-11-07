<?php

namespace App\Modules\Hr\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $user_id
 * @property string|null $employee_number
 * @property string|null $job_title
 * @property string|null $office
 * @property string|null $employment_status
 * @property Carbon|null $join_date
 * @property Carbon|null $end_date
 * @property Carbon|null $date_of_birth
 * @property Carbon|null $ktp_issued_at
 * @property Carbon|null $ktp_expired_at
 * @property Carbon|null $passport_issued_at
 * @property Carbon|null $passport_expired_at
 * @property string|null $ktp_number
 * @property string|null $passport_number
 * @property string|null $npwp_number
 * @property-read int|null $age
 * @property-read string|null $photo_url
 * @property-read User $user
 */
class Employee extends Model
{
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
        return $this->belongsTo(User::class);
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(EmployeeExperience::class)->orderByDesc('start_date');
    }

    /* ---------- Helpers ---------- */

    /** Ambil (atau buat) profil employee untuk sebuah user. */
    public static function forUser(User $user): self
    {
        return $user->employeeProfile();
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

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('employee_number', 'like', "%{$term}%")
                ->orWhere('job_title', 'like', "%{$term}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhereHas('department', fn ($department) => $department
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('code', 'like', "%{$term}%")));
        });
    }
}
