<?php

namespace Webteractive\Passwordless\Http\Controllers\DevLogin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Lists users for the development-only picker. Reachable only when the
 * three-condition dev_login guard in routes/web.php passed at registration.
 */
class IndexController
{
    public function __invoke(Request $request): JsonResponse
    {
        $model = config('passwordless.user_model');
        $column = config('passwordless.user_email_column', 'email');
        $limit = (int) config('passwordless.dev_login.limit', 50);

        $instance = new $model;

        // Read the key from the model rather than assuming `id`, so apps with a
        // uuid/ulid primary key still get a working picker. `name` is optional
        // for the same reason — not every user table has one.
        $columns = [$instance->getKeyName(), $column];

        if (Schema::hasColumn($instance->getTable(), 'name')) {
            $columns[] = 'name';
        }

        $query = $model::query()->orderBy($column);

        if ($q = $request->query('q')) {
            $query->where($column, 'like', '%'.$q.'%');
        }

        // Select only what a picker needs — never password hashes, remember
        // tokens, or two-factor secrets.
        $users = $query->limit($limit)->get(array_unique($columns))
            ->map(fn ($user) => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->{$column},
            ])
            ->values();

        return response()->json(['users' => $users, 'limit' => $limit]);
    }
}
