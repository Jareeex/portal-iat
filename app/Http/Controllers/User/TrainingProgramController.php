<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Crew;
use App\Models\CrewTraining;
use App\Models\TrainingProgram;
use App\Models\TrainingProgramActual;
use App\Models\TrainingProgramPlan;
use App\Models\TrainingType;
use App\Models\User;
use App\Services\AccessService;
use App\Support\Validity;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TrainingProgramController extends Controller
{
    public function __construct(private AccessService $access) {}

    private function canManage(Request $request): bool
    {
        $user = $request->user();

        return $user instanceof User && $this->access->canEdit($user, 'training');
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($this->canManage($request), 403);
    }

    private function checkPeriod(int $year, int $month = 1): void
    {
        abort_unless($year >= 2000 && $year <= 2100 && $month >= 1 && $month <= 12, 404);
    }

    // ---------------------------------------------------------------- grid tahunan
    public function index(Request $request): JsonResponse
    {
        $today = Carbon::today();
        $year = (int) $request->query('year', $today->year);
        $this->checkPeriod($year);
        $manage = $this->canManage($request);

        $plans = TrainingProgramPlan::where('year', $year)->with('crew.user:id,name')->get()->groupBy('program_id');
        $actuals = TrainingProgramActual::where('year', $year)->with('crews.user:id,name')->get()->groupBy('program_id');
        $groups = Crew::groupOptions();

        $sumCells = 0;
        $sumCellsDone = 0;
        $sumCrew = 0;
        $sumCrewDone = 0;

        $programs = TrainingProgram::with('types:id')->orderBy('sort_order')->orderBy('id')->get()
            ->map(function (TrainingProgram $p) use ($plans, $actuals, &$sumCells, &$sumCellsDone, &$sumCrew, &$sumCrewDone) {
                $acts = $actuals->get($p->id, collect());
                $attended = $acts->flatMap(fn ($a) => $a->crews->pluck('id'))->unique()->flip();

                $planRows = $plans->get($p->id, collect())->map(fn ($pl) => [
                    'id' => $pl->id, 'crew_id' => $pl->crew_id, 'name' => $pl->crew->user?->name ?? $pl->crew->name, 'month' => $pl->month,
                    'due_date' => $pl->due_date?->toDateString(), 'overdue' => $pl->overdue,
                    'done' => $attended->has($pl->crew_id),   // crew ini ikut realisasi program ini di tahun tsb
                ])->sortBy('name')->values();

                $planMonths = $planRows->pluck('month')->unique();
                $sumCells += $planMonths->count();
                $sumCellsDone += $planMonths->filter(fn ($m) => $acts->contains('month', $m))->count();
                $sumCrew += $planRows->count();
                $sumCrewDone += $planRows->where('done', true)->count();

                return [
                    'id' => $p->id, 'category' => $p->category, 'name' => $p->name,
                    'type_ids' => $p->types->pluck('id')->values(),
                    'plans' => $planRows,
                    'actuals' => $acts->map(fn ($a) => [
                        'id' => $a->id, 'month' => $a->month,
                        'held_on' => $a->held_on?->toDateString(), 'end_on' => $a->end_on?->toDateString(),
                        'venue' => $a->venue, 'notes' => $a->notes,
                        'crews' => $a->crews->map(fn ($c) => ['id' => $c->id, 'name' => $c->user?->name ?? $c->name])->sortBy('name')->values(),
                    ])->values(),
                ];
            });

        return response()->json([
            'year' => $year,
            'years' => range($today->year - 2, $today->year + 1),
            'now' => ['year' => $today->year, 'month' => $today->month],
            'categories' => TrainingProgram::CATEGORIES,
            'programs' => $programs,
            'summary' => ['cells' => $sumCells, 'cells_done' => $sumCellsDone, 'crew' => $sumCrew, 'crew_done' => $sumCrewDone],
            'crews' => $manage
                ? Crew::with('user:id,name')->orderBy('sort_order')->get()->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->user?->name ?? $c->name,
                    'group_label' => $groups[$c->group]['label'] ?? $c->group ?? 'Other',
                ])->sortBy('name')->values()
                : [],
            'trainingTypes' => $manage
                ? TrainingType::orderBy('sort_order')->get(['id', 'group', 'section', 'name'])->map(fn ($t) => [
                    'id' => $t->id, 'label' => (Crew::GROUPS[$t->group]['label'] ?? $t->group).' · '.$t->section.' · '.$t->name,
                ])
                : [],
            'can' => ['manage' => $manage],
        ]);
    }

    // ---------------------------------------------------------------- PLAN dari last check
    /**
     * Susun plan satu tahun: tiap crew yang punya item training pada program ini ditaruh di bulan jatuh temponya
     * (next check; kalau kosong = last check + masa berlaku). Yang sudah lewat sebelum tahun itu -> Januari (overdue).
     * Hanya MENAMBAH entri yang belum ada, jadi plan yang sudah diubah tidak ditimpa.
     * Jalankan di awal tahun / sebelum realisasi mengubah last check.
     */
    public function generatePlan(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $year = (int) $request->validate(['year' => 'required|integer|min:2000|max:2100'])['year'];
        $created = 0;

        DB::transaction(function () use ($year, &$created) {
            foreach (TrainingProgram::with('types:id,validity')->get() as $program) {
                if ($program->types->isEmpty()) {
                    continue;
                }
                $validity = $program->types->pluck('validity', 'id');

                $due = []; // crew_id => tanggal jatuh tempo paling awal
                foreach (CrewTraining::whereIn('training_type_id', $validity->keys())->get() as $r) {
                    $d = $r->next_check ?? Validity::addTo($r->last_check, $validity[$r->training_type_id]);
                    if ($d && (! isset($due[$r->crew_id]) || $d->lt($due[$r->crew_id]))) {
                        $due[$r->crew_id] = $d;
                    }
                }

                foreach ($due as $crewId => $d) {
                    if ($d->year > $year) {
                        continue;
                    }
                    $plan = TrainingProgramPlan::firstOrCreate(
                        ['program_id' => $program->id, 'crew_id' => $crewId, 'year' => $year],
                        ['month' => $d->year < $year ? 1 : $d->month, 'due_date' => $d->toDateString(), 'overdue' => $d->year < $year],
                    );
                    if ($plan->wasRecentlyCreated) {
                        $created++;
                    }
                }
            }
        });

        return response()->json([
            'message' => "Plan $year disusun dari last check: $created entri baru.",
            'created' => $created,
        ]);
    }

    public function storePlan(Request $request, TrainingProgram $program): JsonResponse
    {
        $this->authorizeManage($request);
        $d = $request->validate(['year' => 'required|integer|min:2000|max:2100', 'month' => 'required|integer|min:1|max:12', 'crew_id' => 'required|exists:crews,id']);

        $plan = TrainingProgramPlan::firstOrNew(['program_id' => $program->id, 'crew_id' => $d['crew_id'], 'year' => $d['year']]);
        $plan->month = $d['month'];
        $plan->save();

        return response()->json(['message' => 'Crew ditambahkan ke plan.']);
    }

    public function updatePlan(Request $request, TrainingProgramPlan $plan): JsonResponse
    {
        $this->authorizeManage($request);
        $plan->update($request->validate(['month' => 'required|integer|min:1|max:12']));

        return response()->json(['message' => 'Plan dipindahkan.']);
    }

    public function destroyPlan(Request $request, TrainingProgramPlan $plan): JsonResponse
    {
        $this->authorizeManage($request);
        $plan->delete();

        return response()->json(['message' => 'Crew dihapus dari plan.']);
    }

    // ---------------------------------------------------------------- ACTUAL (biru), diinput staff training
    public function saveActual(Request $request, TrainingProgram $program, int $year, int $month): JsonResponse
    {
        $this->authorizeManage($request);
        $this->checkPeriod($year, $month);

        $data = $request->validate([
            'held_on' => 'required|date', 'end_on' => 'nullable|date|after_or_equal:held_on',
            'venue' => 'nullable|string|max:150', 'notes' => 'nullable|string|max:1000',
            'crew_ids' => 'nullable|array', 'crew_ids.*' => 'integer|exists:crews,id',
            'update_records' => 'boolean',
        ]);

        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($request, $program, $year, $month, $data, &$updated, &$skipped) {
            $actual = TrainingProgramActual::updateOrCreate(
                ['program_id' => $program->id, 'year' => $year, 'month' => $month],
                ['held_on' => $data['held_on'], 'end_on' => $data['end_on'] ?? null, 'venue' => $data['venue'] ?? null,
                    'notes' => $data['notes'] ?? null, 'user_id' => $request->user()->id],
            );
            $ids = $data['crew_ids'] ?? [];
            $actual->crews()->sync($ids);

            if (! $request->boolean('update_records')) {
                return;
            }

            // Realisasi -> last check peserta = tanggal pelaksanaan, next check = + masa berlaku.
            // Plan tahun depan nanti otomatis ikut dari last check baru ini.
            $last = Carbon::parse($data['end_on'] ?? $data['held_on']);
            $types = $program->types()->get(['training_types.id', 'training_types.validity']);

            foreach ($ids as $crewId) {
                $records = CrewTraining::where('crew_id', $crewId)->whereIn('training_type_id', $types->pluck('id'))->get();
                if ($records->isEmpty()) {
                    $skipped++;

                    continue;
                } // crew belum punya record item ini
                foreach ($records as $rec) {
                    $rec->last_check = $last->toDateString();
                    if ($next = Validity::addTo($last, $types->firstWhere('id', $rec->training_type_id)?->validity)) {
                        $rec->next_check = $next->toDateString();
                    }
                    $rec->save();
                }
                $updated++;
            }
        });

        $msg = 'Realisasi tersimpan.';
        if ($request->boolean('update_records')) {
            $msg .= " Last/next check diperbarui untuk $updated crew".($skipped ? "; $skipped crew dilewati (belum punya record item ini)." : '.');
        }

        return response()->json(['message' => $msg]);
    }

    public function destroyActual(Request $request, TrainingProgramActual $actual): JsonResponse
    {
        $this->authorizeManage($request);
        $actual->delete(); // last/next check crew yang sudah terlanjur diperbarui tidak dikembalikan

        return response()->json(['message' => 'Realisasi dihapus (last check crew tidak diubah).']);
    }

    // ---------------------------------------------------------------- program (baris di grid)
    private function programRules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'category' => ['required', Rule::in(array_keys(TrainingProgram::CATEGORIES))],
            'type_ids' => 'nullable|array', 'type_ids.*' => 'integer|exists:training_types,id',
        ];
    }

    public function storeProgram(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $d = $request->validate($this->programRules());

        $program = TrainingProgram::create(['name' => $d['name'], 'category' => $d['category'], 'sort_order' => (int) TrainingProgram::max('sort_order') + 1]);
        $program->types()->sync($d['type_ids'] ?? []);

        return response()->json(['message' => 'Program ditambahkan. Klik "Susun plan" untuk mengisi plan dari last check.']);
    }

    public function updateProgram(Request $request, TrainingProgram $program): JsonResponse
    {
        $this->authorizeManage($request);
        $d = $request->validate($this->programRules());

        $program->update(['name' => $d['name'], 'category' => $d['category']]);
        $program->types()->sync($d['type_ids'] ?? []);

        return response()->json(['message' => 'Program disimpan.']);
    }

    public function destroyProgram(Request $request, TrainingProgram $program): JsonResponse
    {
        $this->authorizeManage($request);
        $program->delete(); // plan & realisasi program ini ikut terhapus

        return response()->json(['message' => 'Program dihapus.']);
    }
}
