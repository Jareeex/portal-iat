<?php

namespace App\Modules\ItAsset\Support;

use Illuminate\Database\Eloquent\Model;

class ItAsset
{
    /** @return class-string<Model> */
    public static function userModel(): string
    {
        $model = config('it-asset.user_model') ?? config('auth.providers.users.model');
        if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
            throw new \RuntimeException('The IT asset user model must be an Eloquent model class.');
        }

        return $model;
    }
}
