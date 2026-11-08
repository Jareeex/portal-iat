<?php

use App\Models\Department;
use App\Models\User;
use App\Modules\ItTickets\Models\Ticket;
use Laravel\Sanctum\Sanctum;

/**
 * @param  array<string, mixed>  $attributes
 */
function createTicketFor(User $requester, array $attributes = []): Ticket
{
    $year = (int) now()->year;
    $sequence = (int) Ticket::query()->where('year', $year)->max('seq') + 1;

    return Ticket::query()->create($attributes + [
        'number' => sprintf('IT-%d-%04d', $year, $sequence),
        'year' => $year,
        'seq' => $sequence,
        'requester_id' => $requester->id,
        'title' => 'Aplikasi tidak bisa dibuka',
        'category' => 'software',
        'priority' => 'medium',
        'status' => 'open',
        'description' => 'Aplikasi operasional berhenti saat dibuka.',
    ]);
}

function createItDepartment(): Department
{
    return Department::query()->firstOrCreate(
        ['code' => 'IT'],
        ['name' => 'Information Technology', 'has_full_access' => false],
    );
}

test('all authenticated users can submit a ticket for the IT department', function () {
    $requester = User::factory()->create(['is_active' => true]);
    Sanctum::actingAs($requester);

    $this->postJson('/api/it-tickets', [
        'title' => 'Laptop tidak dapat tersambung ke Wi-Fi',
        'category' => 'network',
        'priority' => 'high',
        'description' => 'Laptop kantor tidak dapat tersambung ke Wi-Fi sejak pagi.',
        'location' => 'Kantor pusat',
    ])->assertCreated()
        ->assertJsonPath('ticket.number', 'IT-'.now()->year.'-0001')
        ->assertJsonPath('message', 'Tiket IT-'.now()->year.'-0001 terkirim ke departemen IT.');

    $ticket = Ticket::query()->firstOrFail();
    expect($ticket->requester_id)->toBe($requester->id)
        ->and($ticket->status)->toBe('open')
        ->and($ticket->comments()->where('type', 'event')->exists())->toBeTrue();

    $this->getJson('/api/it-tickets/options')
        ->assertOk()
        ->assertJsonPath('categories.network', 'Jaringan / internet / Wi-Fi');
});

test('users see only their own tickets while IT staff can see the shared queue and poll new tickets', function () {
    $requester = User::factory()->create(['is_active' => true]);
    $otherRequester = User::factory()->create(['is_active' => true]);
    $firstTicket = createTicketFor($requester);
    $otherTicket = createTicketFor($otherRequester);

    Sanctum::actingAs($requester);
    $this->getJson('/api/it-tickets')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('can.it', false);
    $this->getJson("/api/it-tickets/{$firstTicket->id}")
        ->assertOk()
        ->assertJsonPath('ticket.requester.name', $requester->name);
    $this->getJson('/api/it-tickets/alerts')->assertForbidden();
    $this->getJson("/api/it-tickets/{$otherTicket->id}")->assertForbidden();

    $itUser = User::factory()->create([
        'department_id' => createItDepartment()->id,
        'is_active' => true,
    ]);
    Sanctum::actingAs($itUser);

    $this->getJson('/api/it-tickets')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('can.it', true)
        ->assertJsonPath('stats.unassigned', 2);

    $this->getJson('/api/it-tickets/alerts')
        ->assertOk()
        ->assertJsonPath('unassigned_count', 2)
        ->assertJsonPath('new_ticket_count', 0);

    $this->getJson('/api/it-tickets/alerts?after_id=0')
        ->assertOk()
        ->assertJsonPath('new_ticket_count', 2)
        ->assertJsonCount(2, 'new_tickets');
});

test('IT can take tickets and internal comments remain hidden from the requester', function () {
    $requester = User::factory()->create(['is_active' => true]);
    $itUser = User::factory()->create([
        'department_id' => createItDepartment()->id,
        'is_active' => true,
    ]);
    $ticket = createTicketFor($requester, ['status' => 'waiting']);

    Sanctum::actingAs($itUser);
    $this->postJson("/api/it-tickets/{$ticket->id}/take")
        ->assertOk();
    $ticket->refresh();
    expect($ticket->assignee_id)->toBe($itUser->id)
        ->and($ticket->status)->toBe('waiting');

    $this->postJson("/api/it-tickets/{$ticket->id}/comments", [
        'body' => 'Perlu cek log internal.',
        'is_internal' => true,
    ])->assertOk();

    $this->getJson("/api/it-tickets/{$ticket->id}")
        ->assertOk()
        ->assertJsonFragment(['body' => 'Perlu cek log internal.']);

    Sanctum::actingAs($requester);
    $this->getJson("/api/it-tickets/{$ticket->id}")
        ->assertOk()
        ->assertJsonMissing([
            'body' => 'Perlu cek log internal.',
        ]);
    $this->postJson("/api/it-tickets/{$ticket->id}/comments", [
        'body' => 'Saya sudah mencoba kembali.',
    ])->assertOk();

    expect($ticket->fresh()->status)->toBe('in_progress');
});
