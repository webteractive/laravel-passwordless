<?php

namespace Webteractive\Passwordless\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAccount extends Model
{
    protected $table = 'passwordless_social_accounts';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('passwordless.user_model'));
    }
}
