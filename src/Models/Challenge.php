<?php

namespace Webteractive\Passwordless\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $type
 * @property string $hash
 * @property array|null $metadata
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Challenge extends Model
{
    protected $table = 'passwordless_challenges';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('consumed_at')->where('expires_at', '>', now());
    }

    public function scopeExpired(Builder $q): Builder
    {
        return $q->where('expires_at', '<=', now());
    }

    public function scopeConsumed(Builder $q): Builder
    {
        return $q->whereNotNull('consumed_at');
    }

    public function isActive(): bool
    {
        return $this->consumed_at === null && $this->expires_at->isFuture();
    }

    public function markConsumed(): void
    {
        $this->forceFill(['consumed_at' => now()])->save();
    }
}
