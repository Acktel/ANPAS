<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait TracksUserActions
{
    public static function bootTracksUserActions()
    {
        static::creating(function ($model) {
            $userId = session('impersonate') ?? Auth::id();
            if (!$model->created_by) {
                $model->created_by = $userId;
            }
            $model->updated_by = $userId;
        });

        static::updating(function ($model) {
            $userId = session('impersonate') ?? Auth::id();
            $model->updated_by = $userId;
        });
    }
}
