<?php

namespace Webteractive\Passwordless\Support;

class DomainPolicy
{
    /**
     * @param  'passwordless'|'social'  $type
     * @param  'login'|'register'  $action
     */
    public static function allows(string $type, string $action, string $email): bool
    {
        $allowed = (array) config('passwordless.domains.allowed', []);

        if ($allowed === []) {
            return true;
        }

        if (! (bool) config("passwordless.domains.enforce.{$type}.{$action}", false)) {
            return true;
        }

        $at = strrchr($email, '@');
        $domain = $at === false ? '' : strtolower(substr($at, 1));

        return in_array($domain, array_map('strtolower', $allowed), true);
    }

    /**
     * @param  'passwordless'|'social'  $type
     * @param  'login'|'register'  $action
     *
     * @throws DomainNotAllowedException
     */
    public static function check(string $type, string $action, string $email): void
    {
        if (! self::allows($type, $action, $email)) {
            throw new DomainNotAllowedException($email);
        }
    }
}
