<?php

use Illuminate\Support\Facades\DB;

/**
 * `DefaultMagicCodeStrategy::invalidateSibling()` enforces magicCode's "first one
 * used wins" rule, and it finds the sibling solely through a JSON-path where:
 *
 *     ->where('metadata->magic_code_id', $magicCodeId)
 *
 * `metadata` is a real JSON column on MySQL/Postgres but plain TEXT on SQLite, and
 * each driver compiles that expression to noticeably different SQL. The suite only
 * ever runs SQLite, so the compiled form for the drivers consumers actually deploy
 * on is otherwise never looked at.
 *
 * What this pins: the expression still compiles to a JSON extraction keyed on
 * `magic_code_id` on every supported driver, rather than degrading to a comparison
 * against a bare `metadata` column. That degradation is the dangerous one — it would
 * match nothing, `update()` would report 0 rows, nothing would throw, and the sibling
 * credential would stay live until TTL while the user logged in perfectly happily.
 *
 * What this does NOT prove: that the query matches the right rows at runtime. That
 * needs a real server; the behaviour itself is covered on SQLite by ConsumeTest and
 * VerifyTest. This is a canary for grammar drift, nothing more.
 *
 * It reproduces the where clause rather than calling the real method, because driving
 * the real code offline is not possible — `pretend()` still resolves a PDO connection.
 * Keep the clause below in sync if the one in the strategy changes.
 */
it('compiles the sibling lookup as a json extraction', function (string $driver, string $expected) {
    config()->set("database.connections.grammar_{$driver}", [
        'driver' => $driver,
        'host' => '127.0.0.1',
        'database' => 'grammar_probe',
        'username' => 'probe',
        'password' => 'probe',
        'charset' => 'utf8mb4',
        'prefix' => '',
    ]);

    // toSql() compiles through the driver's grammar without opening a connection.
    $sql = DB::connection("grammar_{$driver}")
        ->table('passwordless_challenges')
        ->where('type', 'mc_code')
        ->where('metadata->magic_code_id', 'some-uuid')
        ->whereNull('consumed_at')
        ->toSql();

    expect($sql)->toContain($expected);
})->with([
    'mysql' => ['mysql', 'json_unquote(json_extract(`metadata`, \'$."magic_code_id"\'))'],
    'pgsql' => ['pgsql', '"metadata"->>\'magic_code_id\''],
    'sqlite' => ['sqlite', 'json_extract("metadata", \'$."magic_code_id"\')'],
]);
