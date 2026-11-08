<?php

namespace App\Modules\ItTickets\Models;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int|null $comment_id
 * @property int|null $user_id
 * @property string $original_name
 * @property string $path
 * @property string|null $mime
 * @property int $size
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Ticket $ticket
 * @property-read TicketComment|null $comment
 * @property-read User|null $user
 */
class TicketAttachment extends Model
{
    protected $fillable = ['ticket_id', 'comment_id', 'user_id', 'original_name', 'path', 'mime', 'size'];

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<TicketComment, $this> */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(TicketComment::class, 'comment_id');
    }
}
