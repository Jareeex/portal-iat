<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\CrewTraining;
use App\Services\AccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private AccessService $access) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $pages = $this->access->pagesFor($user);

        if (! $user->isMaster()) {
            $grantedPageIds = $user->pages()->pluck('pages.id');
            $pages = $pages->whereIn('id', $grantedPageIds)->values();
        }

        $crew = $user->crew;
        $crew?->loadMissing('trainingAircrafts');
        $expiredTrainings = $crew
            ? $crew->trainings()
                ->with('type:id,name,group')
                ->whereNotNull('next_check')
                ->whereDate('next_check', '<', today())
                ->orderBy('next_check')
                ->get()
                ->filter(fn (CrewTraining $training): bool => $training->type
                    && $crew->appliesToTrainingType($training->type))
                ->map(fn (CrewTraining $training): array => [
                    'name' => $training->type->name,
                    'next_check' => $training->next_check?->toDateString(),
                ])
                ->values()
            : collect();

        return response()->json([
            'greeting' => "Halo, {$user->name}",
            'pages' => $pages,
            'expired_trainings' => $expiredTrainings,
        ]);
    }
}
