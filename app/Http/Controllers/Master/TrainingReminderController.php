<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\User;
use App\Services\TrainingReminderRecipientService;
use Illuminate\Console\Command;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class TrainingReminderController extends Controller
{
    public function index(TrainingReminderRecipientService $recipientService): JsonResponse
    {
        $trainingPageId = Page::query()->where('key', 'training')->value('id');

        return response()->json(
            $recipientService->recipients($trainingPageId)
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'training_reminder_enabled' => $user->training_reminder_enabled,
                ]),
        );
    }

    public function updateRecipient(
        Request $request,
        User $user,
        TrainingReminderRecipientService $recipientService,
    ): JsonResponse {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);
        $trainingPageId = Page::query()->where('key', 'training')->value('id');
        $eligibleRecipients = $recipientService->recipients($trainingPageId);

        abort_unless($eligibleRecipients->contains('id', $user->id), 404);

        $user->forceFill([
            'training_reminder_enabled' => (bool) $validated['enabled'],
        ])->save();

        return response()->json([
            'id' => $user->id,
            'training_reminder_enabled' => $user->training_reminder_enabled,
        ]);
    }

    public function sendAll(): JsonResponse
    {
        $status = Artisan::call('app:send-training-reminders', ['--all' => true]);
        $output = trim(Artisan::output());

        if ($status !== Command::SUCCESS) {
            return response()->json([
                'message' => $output !== '' ? $output : 'Reminder training gagal dikirim.',
            ], 500);
        }

        return response()->json([
            'message' => $output,
        ]);
    }
}
