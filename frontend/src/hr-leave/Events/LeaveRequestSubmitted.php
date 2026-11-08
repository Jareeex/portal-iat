<?php

namespace App\Modules\Hr\Events;

use App\Modules\Hr\Models\LeaveRequest;
use Illuminate\Foundation\Events\Dispatchable;

/** Dipanggil saat karyawan submit. Pasang listener untuk notif/email ke HR. */
class LeaveRequestSubmitted
{
    use Dispatchable;

    public function __construct(public LeaveRequest $leaveRequest) {}
}
