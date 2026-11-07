<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Crew;
use App\Models\CrewLicenseDocument;
use App\Models\CrewTraining;
use App\Models\TrainingAircraft;
use App\Models\TrainingType;
use App\Models\User;
use App\Services\AccessService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrainingController extends Controller
{
    public function __construct(private AccessService $access) {}

    private function canManage(Request $request): bool
    {
        $user = $request->user();

        return $user !== null && $this->access->canEdit($user, 'training');
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($this->canManage($request), 403);
    }

    /** @return array<string, array{label: string, category: string}> */
    private function groupsForUi(): array
    {
        return Crew::groupOptions();
    }

    public function index(Request $request): JsonResponse
    {
        $today = Carbon::today();

        $groups = $this->groupsForUi();
        $crews = Crew::query()
            ->whereIn('group', array_keys($groups))
            ->with(
                'trainingAircrafts:id,group_key,name',
                'trainings:id,crew_id,training_type_id,next_check',
                'trainings.type:id,name,group',
            )
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (Crew $crew) use ($groups): array {
                $counts = ['expired' => 0, 'soon' => 0, 'valid' => 0];

                foreach ($crew->trainings as $training) {
                    if ($training->type && ! $crew->appliesToTrainingType($training->type)) {
                        continue;
                    }

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
                    'group_label' => $groups[$crew->group]['label'],
                    'category' => $groups[$crew->group]['category'],
                    'pilot_role' => $crew->pilot_role,
                    'aircrafts' => $crew->trainingAircrafts
                        ->map(fn (TrainingAircraft $aircraft): array => [
                            'id' => $aircraft->id,
                            'key' => $aircraft->group_key,
                            'label' => $aircraft->name,
                        ])
                        ->values(),
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
            'aircrafts' => TrainingAircraft::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (TrainingAircraft $aircraft): array => [
                    'id' => $aircraft->id,
                    'key' => $aircraft->group_key,
                    'label' => $aircraft->name,
                ])
                ->values(),
            'groups' => collect($this->groupsForUi())->map(
                fn (array $group, string $key): array => ['key' => $key] + $group,
            )->values(),
            'can' => ['manage' => $this->canManage($request)],
        ]);
    }

    public function show(Request $request, Crew $crew): JsonResponse
    {
        $groups = $this->groupsForUi();
        abort_unless(isset($groups[$crew->group]), 404);
        $crew->loadMissing('user', 'licenseDocuments', 'trainingAircrafts');

        $records = $crew->trainings()->get()->keyBy('training_type_id');
        $sections = [];
        $counts = ['expired' => 0, 'soon' => 0, 'valid' => 0];
        $aircraftGroups = $crew->trainingAircrafts->pluck('group_key')->all();
        if ($aircraftGroups === [] && isset($groups[$crew->group]) && $groups[$crew->group]['category'] === 'Pilot') {
            $aircraftGroups = [$crew->group];
        }
        $trainingGroups = array_values(array_unique([...$aircraftGroups, ...(
            isset(Crew::GROUPS[$crew->group]) ? [$crew->group] : []
        )]));

        foreach (TrainingType::query()->whereIn('group', $trainingGroups)->orderBy('group')->orderBy('sort_order')->get() as $type) {
            if (! $crew->appliesToTrainingType($type)) {
                continue;
            }

            $record = $records->get($type->id);
            $status = CrewTraining::statusFor($record?->next_check);

            if ($status !== null) {
                $counts[$status]++;
            }

            $sectionName = isset($groups[$type->group]) && $groups[$type->group]['category'] === 'Pilot'
                ? $groups[$type->group]['label'].' — '.$type->section
                : $type->section;
            $sections[$sectionName][] = [
                'type_id' => $type->id,
                'name' => $type->name,
                'aircraft_group' => $type->group,
                'validity' => $type->validity,
                'last_check' => $record?->last_check?->toDateString(),
                'next_check' => $record?->next_check?->toDateString(),
                'plan_check' => $record?->plan_check?->toDateString(),
                'days_left' => CrewTraining::daysLeft($record?->next_check),
                'status' => $status,
            ];
        }

        $canManage = $this->canManage($request);

        return response()->json([
            'crew' => [
                'id' => $crew->id,
                'name' => $crew->name,
                'code' => $crew->code,
                'group' => $crew->group,
                'group_label' => $groups[$crew->group]['label'],
                'category' => $groups[$crew->group]['category'],
                'pilot_role' => $crew->pilot_role,
                'position' => $crew->position,
                'aircrafts' => $crew->trainingAircrafts
                    ->map(fn (TrainingAircraft $aircraft): array => [
                        'id' => $aircraft->id,
                        'key' => $aircraft->group_key,
                        'label' => $aircraft->name,
                    ])
                    ->values(),
            ],
            'personal' => $canManage ? $crew->only([
                'dob', 'address', 'passport_no', 'passport_expiry', 'nationality', 'joined_iat', 'licence', 'rating', 'remarks',
            ]) + ['email' => $crew->user?->email] : null,
            'sections' => collect($sections)->map(
                fn (array $items, string $section): array => ['section' => $section, 'items' => $items],
            )->values(),
            'groups' => collect($groups)->map(
                fn (array $group, string $key): array => ['key' => $key] + $group,
            )->values(),
            'aircraft_options' => TrainingAircraft::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (TrainingAircraft $aircraft): array => [
                    'id' => $aircraft->id,
                    'key' => $aircraft->group_key,
                    'label' => $aircraft->name,
                ])
                ->values(),
            'license_documents' => $crew->licenseDocuments
                ->filter(fn (CrewLicenseDocument $document): bool => $canManage || $crew->user_id === $request->user()->id)
                ->map(fn (CrewLicenseDocument $document): array => [
                    'id' => $document->id,
                    'name' => $document->original_name,
                    'uploaded_at' => $document->created_at?->toIso8601String(),
                ])
                ->values(),
            'counts' => $counts,
            'can' => ['manage' => $canManage],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate($this->crewRules(true, (string) $request->input('group')) + [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);
        $crew = DB::transaction(function () use ($data): Crew {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => bin2hex(random_bytes(32)),
                'role' => User::ROLE_USER,
                'is_active' => false,
            ]);

            $crewData = $data;
            unset($crewData['email']);
            $crew = Crew::create($this->withLegacyFields($crewData) + ['user_id' => $user->id]);
            $aircraft = TrainingAircraft::query()->where('group_key', $crew->group)->first();
            if ($aircraft) {
                $crew->trainingAircrafts()->attach($aircraft);
            }
            $user->employeeProfile()->update([
                'job_title' => $this->groupsForUi()[$crew->group]['category'],
            ]);

            return $crew;
        });

        return response()->json(['id' => $crew->id], 201);
    }

    public function storeAircraft(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('training_aircrafts', 'name')],
        ]);

        $aircraft = DB::transaction(function () use ($data): TrainingAircraft {
            $aircraft = TrainingAircraft::create([
                'name' => $data['name'],
                'sort_order' => ((int) TrainingAircraft::max('sort_order')) + 1,
            ]);
            $aircraft->update(['group_key' => 'ac_'.$aircraft->id]);

            return $aircraft;
        });

        return response()->json([
            'id' => $aircraft->id,
            'key' => $aircraft->group_key,
            'label' => $aircraft->name,
            'category' => 'Pilot',
        ], 201);
    }

    public function destroyAircraft(Request $request, TrainingAircraft $aircraft): JsonResponse
    {
        $this->authorizeManage($request);
        abort_unless($aircraft->is_active, 404);
        abort_if(
            $aircraft->crews()->exists()
                || Crew::query()->where('group', $aircraft->group_key)->exists(),
            422,
            'Pindahkan crew dari pesawat ini sebelum menghapusnya.',
        );

        $aircraft->update(['is_active' => false]);

        return response()->json([], 204);
    }

    public function storeCrewAircraft(Request $request, Crew $crew): JsonResponse
    {
        $this->authorizeManage($request);
        abort_unless(($this->groupsForUi()[$crew->group]['category'] ?? null) === 'Pilot', 422);
        $data = $request->validate([
            'aircraft_id' => ['required', 'integer', Rule::exists('training_aircrafts', 'id')
                ->where('is_active', true)],
        ]);
        $aircraft = TrainingAircraft::query()->findOrFail($data['aircraft_id']);
        $crew->trainingAircrafts()->syncWithoutDetaching([$aircraft->id]);

        return response()->json([
            'aircrafts' => $crew->fresh()->trainingAircrafts()
                ->orderBy('sort_order')
                ->get(['training_aircrafts.id', 'group_key', 'name'])
                ->map(fn (TrainingAircraft $assignedAircraft): array => [
                    'id' => $assignedAircraft->id,
                    'key' => $assignedAircraft->group_key,
                    'label' => $assignedAircraft->name,
                ])
                ->values(),
        ], 201);
    }

    public function destroyCrewAircraft(Request $request, Crew $crew, TrainingAircraft $aircraft): JsonResponse
    {
        $this->authorizeManage($request);
        abort_unless($crew->trainingAircrafts()->whereKey($aircraft->id)->exists(), 404);
        abort_if(
            $crew->trainingAircrafts()->count() <= 1,
            422,
            'Crew harus memiliki minimal satu pesawat.',
        );

        DB::transaction(function () use ($crew, $aircraft): void {
            $crew->trainingAircrafts()->detach($aircraft->id);
            if ($crew->group === $aircraft->group_key) {
                $nextAircraft = $crew->trainingAircrafts()->orderBy('sort_order')->firstOrFail();
                $crew->update([
                    'group' => $nextAircraft->group_key,
                    'category' => 'Pilot',
                    'fleet' => $nextAircraft->name,
                ]);
            }
        });

        return response()->json([], 204);
    }

    public function update(Request $request, Crew $crew): JsonResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate($this->crewRules(
            true,
            (string) $request->input('group', $crew->group),
            true,
        ) + [
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($crew->user_id),
            ],
        ]);

        DB::transaction(function () use ($data, $crew): void {
            $crewData = $data;
            unset($crewData['email']);
            $crew->update($this->withLegacyFields($crewData, $crew->group));

            if ($crew->user) {
                $userData = ['name' => $data['name']];
                if (filled($data['email'] ?? null)) {
                    $userData['email'] = $data['email'];
                }
                $crew->user->update($userData);
            } elseif (filled($data['email'] ?? null)) {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => bin2hex(random_bytes(32)),
                    'role' => User::ROLE_USER,
                    'is_active' => false,
                ]);
                $crew->user()->associate($user);
                $crew->save();
            }

            if ($crew->user) {
                $crew->user->employeeProfile()->update([
                    'job_title' => $this->groupsForUi()[$crew->group]['category'],
                ]);
            }
        });

        return response()->json(['id' => $crew->id]);
    }

    public function destroy(Request $request, Crew $crew): JsonResponse
    {
        $this->authorizeManage($request);
        foreach ($crew->licenseDocuments as $document) {
            $this->deleteLicenseFile($document);
        }
        $crew->delete();

        return response()->json([], 204);
    }

    public function storeLicenseDocuments(Request $request, Crew $crew): JsonResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);
        $documents = [];

        foreach ($data['files'] as $file) {
            $path = $file->store('crew-licenses/'.$crew->id, 'local');
            if (! $path) {
                throw new RuntimeException('PDF lisensi gagal disimpan.');
            }

            $documents[] = $crew->licenseDocuments()->create([
                'uploaded_by' => $request->user()->id,
                'original_name' => basename(str_replace('\\', '/', $file->getClientOriginalName())),
                'path' => $path,
            ]);
        }

        return response()->json([
            'documents' => collect($documents)->map(fn (CrewLicenseDocument $document): array => [
                'id' => $document->id,
                'name' => $document->original_name,
                'uploaded_at' => $document->created_at?->toIso8601String(),
            ]),
        ], 201);
    }

    public function showLicenseDocument(
        Request $request,
        Crew $crew,
        CrewLicenseDocument $document,
    ): BinaryFileResponse {
        abort_unless($document->crew_id === $crew->id, 404);
        abort_unless(
            $crew->user_id === $request->user()->id || $this->canManage($request),
            403,
        );
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        $fallbackName = Str::ascii($document->original_name);

        return response()->file(Storage::disk('local')->path($document->path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                'inline',
                $document->original_name,
                $fallbackName !== '' ? $fallbackName : 'license.pdf',
            ),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroyLicenseDocument(
        Request $request,
        Crew $crew,
        CrewLicenseDocument $document,
    ): JsonResponse {
        $this->authorizeManage($request);
        abort_unless($document->crew_id === $crew->id, 404);
        $this->deleteLicenseFile($document);
        $document->delete();

        return response()->json([], 204);
    }

    private function deleteLicenseFile(CrewLicenseDocument $document): void
    {
        $disk = Storage::disk('local');
        if ($disk->exists($document->path) && ! $disk->delete($document->path)) {
            throw new RuntimeException('PDF lisensi gagal dihapus.');
        }
    }

    public function saveRecord(Request $request, Crew $crew, TrainingType $type): JsonResponse
    {
        $this->authorizeManage($request);
        $crew->loadMissing('trainingAircrafts');
        abort_unless($crew->appliesToTrainingType($type), 404);

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
            ->whereIn('group', array_keys($this->groupsForUi()))
            ->when($group, function ($query) use ($group): void {
                if (isset(Crew::GROUPS[$group])) {
                    $query->where('group', $group);
                } else {
                    $query->whereHas('trainingAircrafts', fn ($aircraftQuery) => $aircraftQuery->where('group_key', $group));
                }
            })
            ->with('trainings')
            ->orderBy('sort_order')
            ->get();
        $types = TrainingType::query()->orderBy('sort_order')->get()->groupBy('group');

        return response()->streamDownload(function () use ($crews, $types): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['group', 'crew', 'section', 'training', 'validity', 'last_check', 'next_check', 'plan_check', 'status']);

            foreach ($crews->load('trainingAircrafts') as $crew) {
                $records = $crew->trainings->keyBy('training_type_id');

                foreach ($types as $groupTypes) {
                    foreach ($groupTypes as $type) {
                        if (! $crew->appliesToTrainingType($type)) {
                            continue;
                        }

                        $record = $records->get($type->id);
                        fputcsv($output, [
                            $type->group,
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

        $crews = Crew::query()
            ->whereIn('group', array_keys($this->groupsForUi()))
            ->with('trainingAircrafts')
            ->get()
            ->flatMap(function (Crew $crew): array {
                $groups = $crew->trainingAircrafts->pluck('group_key')->all();
                if ($groups === []) {
                    $groups[] = $crew->group;
                }
                if (isset(Crew::GROUPS[$crew->group])) {
                    $groups[] = $crew->group;
                }

                return collect(array_unique($groups))
                    ->mapWithKeys(fn (string $group): array => [
                        $group.'|'.mb_strtolower($crew->name) => $crew,
                    ])
                    ->all();
            });
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

                if (! $crew->appliesToTrainingType($type)) {
                    $errors[] = "baris {$line}: item training tidak berlaku untuk role crew ini";

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
    private function crewRules(bool $includeGroup, ?string $group = null, bool $optionalGroup = false): array
    {
        $isPilot = ($this->groupsForUi()[$group ?? '']['category'] ?? null) === 'Pilot';

        return [
            ...($includeGroup ? ['group' => [
                ...($optionalGroup ? ['sometimes'] : ['required']),
                'string',
                'in:'.implode(',', array_keys($this->groupsForUi())),
            ]] : []),
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:10'],
            'position' => ['nullable', 'string', 'max:100'],
            'pilot_role' => [
                ...($isPilot ? ($optionalGroup ? ['sometimes', 'required'] : ['required']) : ['nullable']),
                Rule::in(['PIC', 'SIC']),
            ],
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
        $groupInfo = $this->groupsForUi()[$group];
        $data['group'] = $group;
        $data['category'] = $groupInfo['category'];
        $data['fleet'] = $groupInfo['label'];
        $data['position'] ??= '';

        return $data;
    }
}
