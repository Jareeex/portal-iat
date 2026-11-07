<?php

namespace App\Modules\Hr\Console;

use App\Models\User;
use App\Modules\Hr\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class SyncEmployees extends Command
{
    protected $signature = 'hr:sync-employees';

    protected $description = 'Create missing employee profiles for user accounts';

    public function handle(): int
    {
        $count = 0;

        User::query()
            ->whereNotIn('id', Employee::query()->select('user_id'))
            ->chunkById(200, function (Collection $users) use (&$count): void {
                foreach ($users as $user) {
                    Employee::forUser($user);
                    $count++;
                }
            });

        $this->info("Created {$count} missing employee profiles.");

        return self::SUCCESS;
    }
}
