<?php

namespace App\Modules\Hr\Support;

use Illuminate\Foundation\Auth\User;

class Hr
{
    /** @return class-string<User> */
    public static function userModel(): string
    {
        return config('hr.user_model') ?? config('auth.providers.users.model');
    }
}
