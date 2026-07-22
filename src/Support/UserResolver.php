<?php

namespace Webteractive\Passwordless\Support;

class UserResolver
{
    public function findByEmail(string $email): mixed
    {
        $model = config('passwordless.user_model');
        $column = config('passwordless.user_email_column', 'email');

        return $model::query()->where($column, $email)->first();
    }

    public function findOrCreate(string $email): mixed
    {
        if ($user = $this->findByEmail($email)) {
            return $user;
        }

        if (! config('passwordless.auto_create_users', false)) {
            return null;
        }

        $model = config('passwordless.user_model');
        $column = config('passwordless.user_email_column', 'email');

        return $model::query()->create([$column => $email]);
    }
}
