<?php

namespace App\Modules\Hr\Console;

use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Support\Hr;
use Illuminate\Console\Command;

class SyncEmployees extends Command
{
    protected $signature = 'hr:sync-employees';

    protected $description = 'Buat profil employee kosong untuk semua user yang belum punya';

    public function handle(): int
    {
        $count = 0;

        (Hr::userModel())::query()
            ->whereNotIn('id', Employee::withTrashed()->select('user_id'))
            ->chunkById(200, function ($users) use (&$count) {
                foreach ($users as $user) {
                    Employee::forUser($user);
                    $count++;
                }
            });

        $this->info("Selesai. {$count} profil employee dibuat.");

        return self::SUCCESS;
    }
}
