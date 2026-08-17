<?php

namespace Workbench\App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * User model with a uuid primary key and no `name` column — the shape that
 * would break any code assuming `id` and `name` exist.
 */
class UuidUser extends Authenticatable
{
    use Notifiable;

    protected $table = 'uuid_users';

    protected $primaryKey = 'uuid';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    public $timestamps = true;
}
