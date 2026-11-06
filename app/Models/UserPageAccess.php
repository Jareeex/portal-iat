<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UserPageAccess extends Pivot
{
    protected $table = 'user_page_access';

    public $incrementing = true;

    protected $fillable = ['user_id', 'page_id'];
}
