<?php

namespace Workbench\App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * Same `users` table as the plain workbench User, but with Fortify's trait so
 * the 2FA-present code paths can be exercised. Tests opt in by pointing
 * config('passwordless.user_model') at this class.
 */
class TwoFactorUser extends Authenticatable
{
    use Notifiable, TwoFactorAuthenticatable;

    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = true;

    protected $casts = [
        'two_factor_confirmed_at' => 'datetime',
    ];
}
