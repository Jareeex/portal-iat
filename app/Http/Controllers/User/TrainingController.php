<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Crew;
use App\Models\TrainingItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;

class TrainingController extends Controller
{
    public function index(): JsonResponse
    {
        $today = Carbon::today();

        $crews = Crew::query()
            ->with(['items' => fn (HasMany $items) => $items->orderBy('next_check')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (Crew $crew) use ($today): array {
                $items = $crew->items->map(function (TrainingItem $item) use ($today): array {
                    $daysLeft = (int) $today->diffInDays($item->next_check->copy()->startOfDay(), false);

                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'next_check' => $item->next_check->toDateString(),
                        'days_left' => $daysLeft,
                        'status' => $daysLeft < 0 ? 'expired' : ($daysLeft <= 60 ? 'soon' : 'valid'),
                    ];
                });

                return [
                    'id' => $crew->id,
                    'name' => $crew->name,
                    'code' => $crew->code,
                    'category' => $crew->category,
                    'fleet' => $crew->fleet,
                    'position' => $crew->position,
                    'expired' => $items->where('status', 'expired')->count(),
                    'soon' => $items->where('status', 'soon')->count(),
                    'valid' => $items->where('status', 'valid')->count(),
                    'items' => $items->values(),
                ];
            });

        return response()->json([
            'crews' => $crews,
            'summary' => [
                'crew' => $crews->count(),
                'expired' => $crews->sum('expired'),
                'soon' => $crews->sum('soon'),
                'valid' => $crews->sum('valid'),
            ],
            'period' => $today->locale('id')->translatedFormat('F Y'),
        ]);
    }
}
