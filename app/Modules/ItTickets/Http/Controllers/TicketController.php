<?php

namespace App\Modules\ItTickets\Http\Controllers;

use App\Models\User;
use App\Modules\ItTickets\Models\Ticket;
use App\Modules\ItTickets\Models\TicketAttachment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketController
{
    private const FILE_RULES = [
        'files' => ['nullable', 'array', 'max:3'],
        'files.*' => ['file', 'max:5120', 'mimes:jpg,jpeg,png,gif,webp,pdf,txt,log,doc,docx,xls,xlsx'],
    ];

    private function isItStaff(User $user): bool
    {
        $user->loadMissing('department');

        return $user->isMaster() || $user->department?->code === 'IT';
    }

    private function authorizeView(User $user, Ticket $ticket): void
    {
        abort_unless($this->isItStaff($user) || $ticket->requester_id === $user->id, 403);
    }

    /**
     * @return Collection<int, User>
     */
    private function staff(): Collection
    {
        return User::query()
            ->where(function (Builder $query): void {
                $query->where('role', User::ROLE_MASTER)
                    ->orWhereHas('department', fn (Builder $department): Builder => $department->where('code', 'IT'));
            })
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function addEvent(Ticket $ticket, User $user, string $body): void
    {
        $ticket->comments()->create(['user_id' => $user->id, 'type' => 'event', 'body' => $body]);
    }

    private function markResponded(Ticket $ticket, User $user): void
    {
        if ($this->isItStaff($user) && $ticket->first_response_at === null) {
            $ticket->first_response_at = now();
        }
    }

    /**
     * @param  array<int, UploadedFile>|null  $files
     */
    private function storeFiles(?array $files, Ticket $ticket, User $user, ?int $commentId = null): void
    {
        foreach ($files ?? [] as $file) {
            $path = $file->store("tickets/{$ticket->id}", 'local');
            if ($path === false) {
                throw new RuntimeException('Lampiran tiket gagal disimpan.');
            }

            $ticket->attachments()->create([
                'comment_id' => $commentId,
                'user_id' => $user->id,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketSummary(Ticket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'number' => $ticket->number,
            'title' => $ticket->title,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'requester' => $ticket->requester->name,
            'assignee' => $ticket->assignee?->name,
            'created_at' => $ticket->created_at?->toIso8601String(),
            'updated_at' => $ticket->updated_at?->toIso8601String(),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $isItStaff = $this->isItStaff($user);
        $filters = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['active', 'all', ...array_keys(Ticket::STATUSES)])],
            'priority' => ['nullable', 'string', Rule::in(array_keys(Ticket::PRIORITIES))],
            'category' => ['nullable', 'string', Rule::in(array_keys(Ticket::CATEGORIES))],
            'assignee' => ['nullable', 'string', 'max:20'],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $status = $filters['status'] ?? 'active';
        $base = Ticket::query()->when(! $isItStaff, fn (Builder $query): Builder => $query->where('requester_id', $user->id));
        $query = (clone $base)->with(['requester:id,name', 'assignee:id,name'])
            ->when($status === 'active', fn (Builder $builder): Builder => $builder->whereIn('status', Ticket::ACTIVE))
            ->when(isset(Ticket::STATUSES[$status]), fn (Builder $builder): Builder => $builder->where('status', $status))
            ->when(isset($filters['priority']), fn (Builder $builder): Builder => $builder->where('priority', $filters['priority']))
            ->when(isset($filters['category']), fn (Builder $builder): Builder => $builder->where('category', $filters['category']));

        if ($isItStaff && isset($filters['assignee'])) {
            $query->when($filters['assignee'] === 'me', fn (Builder $builder): Builder => $builder->where('assignee_id', $user->id))
                ->when($filters['assignee'] === 'none', fn (Builder $builder): Builder => $builder->whereNull('assignee_id'))
                ->when(ctype_digit($filters['assignee']), fn (Builder $builder): Builder => $builder->where('assignee_id', (int) $filters['assignee']));
        }

        if (($filters['q'] ?? '') !== '') {
            $term = $filters['q'];
            $query->where(function (Builder $builder) use ($term, $isItStaff): void {
                $builder->where('title', 'like', "%{$term}%")->orWhere('number', 'like', "%{$term}%");
                if ($isItStaff) {
                    $builder->orWhereHas('requester', fn (Builder $requester): Builder => $requester->where('name', 'like', "%{$term}%"));
                }
            });
        }

        $tickets = $query->latest('updated_at')->paginate(20)->withQueryString();
        $counts = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $active = (clone $base)->whereIn('status', Ticket::ACTIVE);
        $stats = [
            'open' => (int) ($counts['open'] ?? 0),
            'in_progress' => (int) ($counts['in_progress'] ?? 0),
            'waiting' => (int) ($counts['waiting'] ?? 0),
            'resolved' => (int) ($counts['resolved'] ?? 0),
            'closed' => (int) ($counts['closed'] ?? 0),
            'unassigned' => (clone $base)->where('status', 'open')->whereNull('assignee_id')->count(),
            'critical' => (clone $active)->where('priority', 'critical')->count(),
        ];

        return response()->json([
            'data' => $tickets->getCollection()->map(fn (Ticket $ticket): array => $this->ticketSummary($ticket))->values(),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
            'stats' => $stats,
            'filters' => [
                'status' => $status,
                'priority' => $filters['priority'] ?? null,
                'category' => $filters['category'] ?? null,
                'assignee' => $isItStaff ? ($filters['assignee'] ?? null) : null,
                'q' => $filters['q'] ?? '',
            ],
            'categories' => Ticket::CATEGORIES,
            'staff' => $isItStaff ? $this->staff() : [],
            'can' => ['it' => $isItStaff],
        ]);
    }

    public function alerts(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($this->isItStaff($user), 403);

        $filters = $request->validate(['after_id' => ['nullable', 'integer', 'min:0']]);
        $latestId = (int) (Ticket::query()->max('id') ?? 0);
        $newTickets = isset($filters['after_id'])
            ? Ticket::query()->where('id', '>', $filters['after_id'])->orderByDesc('id')->limit(5)
                ->get(['id', 'number', 'title', 'created_at'])
                ->map(fn (Ticket $ticket): array => [
                    'id' => $ticket->id,
                    'number' => $ticket->number,
                    'title' => $ticket->title,
                    'created_at' => $ticket->created_at?->toIso8601String(),
                ])
                ->values()
            : collect();

        return response()->json([
            'unassigned_count' => Ticket::query()->where('status', 'open')->whereNull('assignee_id')->count(),
            'latest_ticket_id' => $latestId,
            'new_ticket_count' => isset($filters['after_id'])
                ? Ticket::query()->where('id', '>', $filters['after_id'])->count()
                : 0,
            'new_tickets' => $newTickets,
        ]);
    }

    public function options(): JsonResponse
    {
        return response()->json([
            'categories' => Ticket::CATEGORIES,
            'priorities' => Ticket::PRIORITIES,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(array_keys(Ticket::CATEGORIES))],
            'priority' => ['required', Rule::in(array_keys(Ticket::PRIORITIES))],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'location' => ['nullable', 'string', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
        ] + self::FILE_RULES);

        $ticket = DB::transaction(function () use ($data, $request, $user): Ticket {
            $year = (int) now()->year;
            $sequence = (int) Ticket::query()->where('year', $year)->lockForUpdate()->max('seq') + 1;
            $ticket = Ticket::query()->create(Arr::except($data, ['files']) + [
                'requester_id' => $user->id,
                'year' => $year,
                'seq' => $sequence,
                'number' => sprintf('IT-%d-%04d', $year, $sequence),
            ]);
            $this->addEvent($ticket, $user, 'Tiket dibuat.');
            $this->storeFiles($request->file('files'), $ticket, $user);

            return $ticket;
        });

        return response()->json([
            'message' => "Tiket {$ticket->number} terkirim ke departemen IT.",
            'ticket' => $this->ticketSummary($ticket->load(['requester:id,name', 'assignee:id,name'])),
        ], 201);
    }

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $this->authorizeView($user, $ticket);
        $isItStaff = $this->isItStaff($user);

        $ticket->load([
            'requester:id,name,email',
            'assignee:id,name',
            'attachments' => function (HasMany $query): void {
                $query->whereNull('comment_id');
            },
            'comments' => function (HasMany $query) use ($isItStaff): void {
                if (! $isItStaff) {
                    $query->where('type', '!=', 'internal');
                }
                $query->with(['user:id,name', 'attachments'])->orderBy('id');
            },
        ]);

        $attachmentPayload = fn (TicketAttachment $attachment): array => [
            'id' => $attachment->id,
            'name' => $attachment->original_name,
            'size' => $attachment->size,
            'url' => "/api/it-tickets/attachments/{$attachment->id}",
        ];
        $ticketPayload = array_merge($this->ticketSummary($ticket), [
            'description' => $ticket->description,
            'location' => $ticket->location,
            'contact_phone' => $ticket->contact_phone,
            'resolution' => $ticket->resolution,
            'requester' => ['name' => $ticket->requester->name, 'email' => $ticket->requester->email],
            'assignee' => $ticket->assignee ? ['id' => $ticket->assignee->id, 'name' => $ticket->assignee->name] : null,
            'first_response_at' => $ticket->first_response_at?->toIso8601String(),
            'resolved_at' => $ticket->resolved_at?->toIso8601String(),
            'closed_at' => $ticket->closed_at?->toIso8601String(),
            'attachments' => $ticket->attachments->map($attachmentPayload)->values(),
        ]);
        $timeline = $ticket->comments->map(fn ($comment): array => [
            'id' => $comment->id,
            'type' => $comment->type,
            'body' => $comment->body,
            'user' => $comment->user?->name,
            'created_at' => $comment->created_at?->toIso8601String(),
            'attachments' => $comment->attachments->map($attachmentPayload)->values(),
        ])->values();
        $isRequester = $ticket->requester_id === $user->id;

        return response()->json([
            'ticket' => $ticketPayload,
            'timeline' => $timeline,
            'staff' => $isItStaff ? $this->staff() : [],
            'categories' => Ticket::CATEGORIES,
            'can' => [
                'it' => $isItStaff,
                'comment' => $isItStaff || $ticket->status !== 'closed',
                'close' => $isRequester && $ticket->status === 'resolved',
                'reopen' => $isRequester && in_array($ticket->status, ['resolved', 'closed'], true),
            ],
        ]);
    }

    public function update(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($this->isItStaff($user), 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Ticket::STATUSES))],
            'priority' => ['required', Rule::in(array_keys(Ticket::PRIORITIES))],
            'category' => ['required', Rule::in(array_keys(Ticket::CATEGORIES))],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'resolution' => ['nullable', 'string', 'max:5000', Rule::requiredIf($request->input('status') === 'resolved')],
        ]);
        if (isset($data['assignee_id']) && ! $this->staff()->contains('id', (int) $data['assignee_id'])) {
            throw ValidationException::withMessages(['assignee_id' => ['Penanggung jawab harus anggota departemen IT.']]);
        }

        $ticket->fill($data);
        $labels = ['status' => 'Status', 'priority' => 'Prioritas', 'category' => 'Kategori'];
        $maps = ['status' => Ticket::STATUSES, 'priority' => Ticket::PRIORITIES, 'category' => Ticket::CATEGORIES];
        $events = [];
        foreach ($ticket->getDirty() as $field => $newValue) {
            $oldValue = $ticket->getOriginal($field);
            if (isset($maps[$field])) {
                $events[] = "{$labels[$field]}: {$maps[$field][$oldValue]} → {$maps[$field][$newValue]}";
            } elseif ($field === 'assignee_id') {
                $oldName = $oldValue === null
                    ? 'belum ada'
                    : User::query()->whereKey($oldValue)->firstOrFail()->name;
                $newName = $newValue === null
                    ? 'belum ada'
                    : User::query()->whereKey($newValue)->firstOrFail()->name;
                $events[] = "Ditangani oleh: {$oldName} → {$newName}";
            } elseif ($field === 'resolution') {
                $events[] = 'Catatan penyelesaian diperbarui.';
            }
        }

        if ($ticket->isDirty('status')) {
            $ticket->resolved_at = in_array($ticket->status, ['resolved', 'closed'], true)
                ? ($ticket->resolved_at ?? now())
                : null;
            $ticket->closed_at = $ticket->status === 'closed' ? now() : null;
        }
        if ($events !== []) {
            $this->markResponded($ticket, $user);
        }

        DB::transaction(function () use ($ticket, $events, $user): void {
            $ticket->save();
            foreach ($events as $event) {
                $this->addEvent($ticket, $user, $event);
            }
        });

        return response()->json(['message' => $events !== [] ? 'Tiket diperbarui.' : 'Tidak ada perubahan.']);
    }

    public function take(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($this->isItStaff($user), 403);
        abort_if($ticket->status === 'closed', 422, 'Tiket sudah ditutup.');

        $ticket->assignee_id = $user->id;
        if ($ticket->status === 'open') {
            $ticket->status = 'in_progress';
        }
        $this->markResponded($ticket, $user);

        DB::transaction(function () use ($ticket, $user): void {
            $ticket->save();
            $this->addEvent($ticket, $user, "Tiket diambil oleh {$user->name}.");
        });

        return response()->json(['message' => 'Tiket diambil.']);
    }

    public function comment(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $this->authorizeView($user, $ticket);
        $isItStaff = $this->isItStaff($user);

        if (! $isItStaff && $ticket->status === 'closed') {
            throw ValidationException::withMessages(['body' => ['Tiket sudah ditutup. Buka kembali untuk menambah pesan.']]);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'is_internal' => ['sometimes', 'boolean'],
        ] + self::FILE_RULES);
        $isInternal = $isItStaff && $request->boolean('is_internal');

        DB::transaction(function () use ($request, $ticket, $data, $user, $isItStaff, $isInternal): void {
            $comment = $ticket->comments()->create([
                'user_id' => $user->id,
                'type' => $isInternal ? 'internal' : 'comment',
                'body' => $data['body'],
            ]);
            $this->storeFiles($request->file('files'), $ticket, $user, $comment->id);

            if (! $isItStaff && $ticket->status === 'waiting') {
                $ticket->status = 'in_progress';
                $this->addEvent($ticket, $user, 'Status: Menunggu user → Diproses (user membalas).');
            }
            if ($isItStaff && ! $isInternal) {
                $this->markResponded($ticket, $user);
            }
            $ticket->save();
            $ticket->touch();
        });

        return response()->json(['message' => $isInternal ? 'Catatan internal disimpan.' : 'Pesan terkirim.']);
    }

    public function close(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($ticket->requester_id === $user->id && $ticket->status === 'resolved', 403);

        DB::transaction(function () use ($ticket, $user): void {
            $ticket->update(['status' => 'closed', 'closed_at' => now()]);
            $this->addEvent($ticket, $user, 'Tiket ditutup oleh pemohon (masalah selesai).');
        });

        return response()->json(['message' => 'Terima kasih, tiket ditutup.']);
    }

    public function reopen(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless(
            $ticket->requester_id === $user->id && in_array($ticket->status, ['resolved', 'closed'], true),
            403,
        );

        DB::transaction(function () use ($ticket, $user): void {
            $ticket->update(['status' => 'open', 'resolved_at' => null, 'closed_at' => null]);
            $this->addEvent($ticket, $user, 'Tiket dibuka kembali oleh pemohon.');
        });

        return response()->json(['message' => 'Tiket dibuka kembali.']);
    }

    public function attachment(Request $request, TicketAttachment $attachment): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $this->authorizeView($user, $attachment->ticket);
        if (! $this->isItStaff($user) && $attachment->comment?->type === 'internal') {
            abort(403);
        }

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }
}
