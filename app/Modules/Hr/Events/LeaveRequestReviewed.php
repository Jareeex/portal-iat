<?php

namespace App\Modules\Hr\Events;

use App\Modules\Hr\Models\LeaveRequest;
use Illuminate\Foundation\Events\Dispatchable;

class LeaveRequestReviewed
{
    use Dispatchable;

    public function __construct(public LeaveRequest $leaveRequest) {}
}
