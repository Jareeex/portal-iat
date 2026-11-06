<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Crew;
use App\Models\CrewTraining;
use App\Models\TrainingType;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrainingController extends Controller
{
    private function canManage(Request $request): bool
    {
        return $request->user()?->isMaster() ?? false;
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($this->canManage($request), 403);
    }

    /** @return array<string, array{label: string, category: string}> */
    private function groupsForUi(): array
    {
        return Crew::GROUPS;
    }

    public function index(Request $request): JsonResponse
    {
        $today = Carbon::today();

        $crews = Crew::query()
            ->whereIn('group', array_keys(Crew::GROUPS))
            ->with('trainings:id,crew_id,next_check')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (Crew $crew): array {
                $counts = ['expired' => 0, 'soon' => 0, 'valid' => 0];

                foreach ($crew->trainings as $training) {
                    $status = CrewTraining::statusFor($training->next_check);
                    if ($status !== null) {
                        $counts[$status]++;
                    }
                }

                return [
                    'id' => $crew->id,
                    'name' => $crew->name,
                    'code' => $crew->code,
                    'group' => $crew->group,
                    'group_label' => Crew::GROUPS[$crew->group]['label'],
                    'category' => Crew::GROUPS[$crew->group]['category'],
                    'position' => $crew->position,
                ] + $counts;
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
            'groups' => collect($this->groupsForUi())->map(
                fn (array $group, string $key): array => ['key' => $key] + $group,
            )->values(),
            'can' => ['manage' => $this->canManage($request)],
        ]);
    }

    public function show(Request $request, Crew $crew): JsonResponse
    {
        abort_unless(isset(Crew::GROUPS[$crew->group]), 404);

        $records = $crew->trainings()->get()->keyBy('training_type_id');
        $sections = [];
        $counts = ['expired' => 0, 'soon' => 0, 'valid' => 0];

        foreach (TrainingType::query()->where('group', $crew->group)->orderBy('sort_order')->get() as $type) {
            $record = $records->get($type->id);
            $status = CrewTraining::statusFor($record?->next_check);

            if ($status !== null) {
                $counts[$status]++;
            }

            $sections[$type->section][] = [
                'type_id' => $type->id,
                'name' => $type->name,
                'validity' => $type->validity,
                'last_check' => $record?->last_check?->toDateString(),
                'next_check' => $record?->next_check?->toDateString(),
                'plan_check' => $record?->plan_check?->toDateString(),
                'days_left' => CrewTraining::daysLeft($record?->next_check),
                'status' => $status,
            ];
        }

        $isMaster = $this->canManage($request);

        return response()->json([
            'crew' => [
                'id' => $crew->id,
                'name' => $crew->name,
                'code' => $crew->code,
                'group' => $crew->group,
                'group_label' => Crew::GROUPS[$crew->group]['label'],
                'position' => $crew->position,
            ],
            'personal' => $isMaster ? $crew->only([
                'dob', 'address', 'passport_no', 'passport_expiry', 'nationality', 'joined_iat', 'licence', 'rating', 'remarks',
            ]) : null,
            'sections' => collect($sections)->map(
                fn (array $items, string $section): array => ['section' => $section, 'items' => $items],
            )->values(),
            'counts' => $counts,
            'can' => ['manage' => $isMaster],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate($this->crewRules(true));
        $crew = Crew::create($this->withLegacyFields($data));

        return response()->json(['id' => $crew->id], 201);
    }

    public function update(Request $request, Crew $crew): JsonResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate($this->crewRules(false));
        $crew->update($this->withLegacyFields($data, $crew->group));

        return response()->json(['id' => $crew->id]);
    }

    public function destroy(Request $request, Crew $crew): JsonResponse
    {
        $this->authorizeManage($request);
        $crew->delete();

        return response()->json([], 204);
    }

    public function saveRecord(Request $request, Crew $crew, TrainingType $type): JsonResponse
    {
        $this->authorizeManage($request);
        abort_unless($type->group === $crew->group, 404);

        $data = $request->validate([
            'last_check' => ['nullable', 'date'],
            'next_check' => ['nullable', 'date'],
            'plan_check' => ['nullable', 'date'],
        ]);
        $keys = ['crew_id' => $crew->id, 'training_type_id' => $type->id];

        if (! array_filter($data)) {
            CrewTraining::query()->where($keys)->delete();
        } else {
            CrewTraining::updateOrCreate($keys, $data);
        }

        return response()->json(['message' => 'Tersimpan.']);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeManage($request);
        $group = $request->query('group');
        $crews = Crew::query()
            ->whereIn('group', array_keys(Crew::GROUPS))
            ->when($group, fn ($query) => $query->where('group', $group))
            ->with('trainings')
            ->orderBy('sort_order')
            ->get();
        $types = TrainingType::query()->orderBy('sort_order')->get()->groupBy('group');

        return response()->streamDownload(function () use ($crews, $types): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['group', 'crew', 'section', 'training', 'validity', 'last_check', 'next_check', 'plan_check', 'status']);

            foreach ($crews as $crew) {
                $records = $crew->trainings->keyBy('training_type_id');

                foreach ($types[$crew->group] ?? [] as $type) {
                    $record = $records->get($type->id);
                    fputcsv($output, [
                        $crew->group,
                        $crew->name,
                        $type->section,
                        $type->name,
                        $type->validity,
                        $record?->last_check?->toDateString(),
                        $record?->next_check?->toDateString(),
                        $record?->plan_check?->toDateString(),
                        CrewTraining::statusFor($record?->next_check),
                    ]);
                }
            }

            fclose($output);
        }, 'training-record-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        if ($handle === false) {
            return response()->json(['message' => 'File tidak dapat dibaca.'], 422);
        }

        $header = array_map(fn ($value): string => trim(ltrim((string) $value, "\xEF\xBB\xBF")), fgetcsv($handle) ?: []);
        $required = ['group', 'crew', 'section', 'training', 'last_check', 'next_check', 'plan_check'];

        if (array_diff($required, $header)) {
            fclose($handle);

            return response()->json(['message' => 'Kolom CSV tidak sesuai. Gunakan file hasil export.'], 422);
        }

        $crews = Crew::query()->whereIn('group', array_keys(Crew::GROUPS))->get()->keyBy(
            fn (Crew $crew): string => $crew->group.'|'.mb_strtolower($crew->name),
        );
        $types = TrainingType::query()->get()->keyBy(
            fn (TrainingType $type): string => $type->group.'|'.mb_strtolower($type->section.'|'.$type->name),
        );
        $processed = 0;
        $errors = [];

        DB::transaction(function () use ($handle, $header, $crews, $types, &$processed, &$errors): void {
            for ($line = 2; ($row = fgetcsv($handle)) !== false; $line++) {
                $values = array_slice(array_pad($row, count($header), null), 0, count($header));
                $record = array_combine($header, $values);
                $crew = $crews->get($record['group'].'|'.mb_strtolower(trim((string) $record['crew'])));
                $type = $types->get($record['group'].'|'.mb_strtolower(trim((string) $record['section'].'|'.(string) $record['training'])));

                if (! $crew || ! $type) {
                    $errors[] = "baris {$line}: crew atau item training tidak ditemukan";

                    continue;
                }

                try {
                    $dates = [];
                    foreach (['last_check', 'next_check', 'plan_check'] as $key) {
                        $value = trim((string) $record[$key]);
                        $dates[$key] = $value === '' ? null : Carbon::parse($value)->toDateString();
                    }
                } catch (\Throwable) {
                    $errors[] = "baris {$line}: format tanggal salah";

                    continue;
                }

                $keys = ['crew_id' => $crew->id, 'training_type_id' => $type->id];
                if (! array_filter($dates)) {
                    CrewTraining::query()->where($keys)->delete();
                } else {
                    CrewTraining::updateOrCreate($keys, $dates);
                }
                $processed++;
            }
        });
        fclose($handle);

        return response()->json([
            'message' => 'Import selesai: '.$processed.' baris diproses'.($errors ? ', '.count($errors).' dilewati.' : '.'),
            'errors' => array_slice($errors, 0, 3),
        ]);
    }

    /** @return array<string, mixed> */
    private function crewRules(bool $includeGroup): array
    {
        return [
            ...($includeGroup ? ['group' => ['required', 'in:'.implode(',', array_keys(Crew::GROUPS))]] : []),
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:10'],
            'position' => ['nullable', 'string', 'max:100'],
            'rating' => ['nullable', 'string', 'max:100'],
            'licence' => ['nullable', 'string', 'max:100'],
            'dob' => ['nullable', 'date'],
            'nationality' => ['nullable', 'string', 'max:50'],
            'joined_iat' => ['nullable', 'date'],
            'passport_no' => ['nullable', 'string', 'max:50'],
            'passport_expiry' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:500'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function withLegacyFields(array $data, ?string $existingGroup = null): array
    {
        $group = $data['group'] ?? $existingGroup;
        $groupInfo = Crew::GROUPS[$group];
        $data['group'] = $group;
        $data['category'] = $groupInfo['category'];
        $data['fleet'] = $groupInfo['label'];
        $data['position'] ??= '';

        return $data;
    }
}
