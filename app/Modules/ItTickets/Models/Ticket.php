<?php

namespace App\Modules\ItTickets\Models;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $number
 * @property int $year
 * @property int $seq
 * @property int $requester_id
 * @property int|null $assignee_id
 * @property string $title
 * @property string $category
 * @property string $priority
 * @property string $status
 * @property string $description
 * @property string|null $location
 * @property string|null $contact_phone
 * @property string|null $resolution
 * @property CarbonInterface|null $first_response_at
 * @property CarbonInterface|null $resolved_at
 * @property CarbonInterface|null $closed_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read User $requester
 * @property-read User|null $assignee
 * @property-read Collection<int, TicketComment> $comments
 * @property-read Collection<int, TicketAttachment> $attachments
 */
class Ticket extends Model
{
    public const STATUSES = [
        'open' => 'Baru',
        'in_progress' => 'Diproses',
        'waiting' => 'Menunggu user',
        'resolved' => 'Teratasi',
        'closed' => 'Ditutup',
    ];

    public const ACTIVE = ['open', 'in_progress', 'waiting'];

    public const PRIORITIES = [
        'low' => 'Rendah',
        'medium' => 'Sedang',
        'high' => 'Tinggi',
        'critical' => 'Kritis',
    ];

    public const CATEGORIES = [
        'hardware' => 'Perangkat (PC / laptop)',
        'software' => 'Aplikasi / software',
        'network' => 'Jaringan / internet / Wi-Fi',
        'account' => 'Akun & akses (login, password)',
        'email' => 'Email',
        'printer' => 'Printer / scanner',
        'ops_system' => 'Sistem operasional (flight ops)',
        'other' => 'Lainnya',
    ];

    protected $fillable = [
        'number',
        'year',
        'seq',
        'requester_id',
        'assignee_id',
        'title',
        'category',
        'priority',
        'status',
        'description',
        'location',
        'contact_phone',
        'resolution',
        'first_response_at',
        'resolved_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** @return HasMany<TicketComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    /** @return HasMany<TicketAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }
}
