<?php

namespace Webteractive\Passwordless\Support;

use Illuminate\Http\Request;

/**
 * Single owner of the remember-me flag.
 *
 * The flag is chosen at send time (a checkbox on the email form) but consumed in
 * a later request for magic links, so it travels in challenge metadata. The
 * strategies read it from there and stash it on the request; controllers then
 * pick it up via resolve(). Keeping both sides here means the channel is
 * explicit and testable rather than an undocumented convention.
 */
class RememberFlag
{
    public const ATTRIBUTE = 'passwordless.remember';

    public function enabled(): bool
    {
        return (bool) config('passwordless.remember.enabled', true);
    }

    public function stash(Request $request, bool $remember): void
    {
        $request->attributes->set(self::ATTRIBUTE, $remember);
    }

    public function stashed(Request $request): ?bool
    {
        if (! $request->attributes->has(self::ATTRIBUTE)) {
            return null;
        }

        return (bool) $request->attributes->get(self::ATTRIBUTE);
    }

    /**
     * Resolution order: a value stashed by the strategy, else a `remember` key
     * on the current request, else the challenge metadata, else false. Always
     * false when the capability is switched off.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function resolve(Request $request, ?array $metadata = null): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        if (($stashed = $this->stashed($request)) !== null) {
            return $stashed;
        }

        if ($request->has('remember')) {
            return $request->boolean('remember');
        }

        return (bool) ($metadata['remember'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function fromContext(array $context): bool
    {
        return $this->enabled() && (bool) ($context['remember'] ?? false);
    }
}
