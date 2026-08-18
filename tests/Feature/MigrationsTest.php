<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

function avatarWideningMigration(): Migration
{
    return require __DIR__.'/../../database/migrations/widen_passwordless_social_accounts_avatar.php.stub';
}

it('creates passwordless_challenges table with expected columns', function () {
    expect(Schema::hasTable('passwordless_challenges'))->toBeTrue();

    foreach (['id', 'user_id', 'type', 'hash', 'metadata', 'expires_at', 'consumed_at', 'created_at', 'updated_at'] as $col) {
        expect(Schema::hasColumn('passwordless_challenges', $col))->toBeTrue("missing column: {$col}");
    }
});

it('creates passwordless_social_accounts table with expected columns', function () {
    expect(Schema::hasTable('passwordless_social_accounts'))->toBeTrue();

    foreach (['id', 'user_id', 'provider', 'provider_id', 'email', 'name', 'nickname', 'avatar', 'token', 'refresh_token', 'expires_at', 'created_at', 'updated_at'] as $col) {
        expect(Schema::hasColumn('passwordless_social_accounts', $col))->toBeTrue("missing column: {$col}");
    }
});

it('declares avatar as text on a fresh install', function () {
    expect(Schema::getColumnType('passwordless_social_accounts', 'avatar'))->toBe('text');
});

/**
 * The create stub already declares `text`, so asserting the built schema does not
 * prove the widening migration works — existing installs are the ones that need
 * it. Rebuild the column as it shipped before 0.1.5 and migrate against that.
 */
it('widens an already-narrow avatar column', function () {
    Schema::drop('passwordless_social_accounts');

    Schema::create('passwordless_social_accounts', function (Blueprint $table) {
        $table->id();
        $table->string('avatar')->nullable();
    });

    expect(Schema::getColumnType('passwordless_social_accounts', 'avatar'))->not->toBe('text');

    $migration = avatarWideningMigration();

    $migration->up();

    expect(Schema::getColumnType('passwordless_social_accounts', 'avatar'))->toBe('text');

    $migration->down();

    expect(Schema::getColumnType('passwordless_social_accounts', 'avatar'))->not->toBe('text');
});

/**
 * Apps that never published the social table must not be handed a migration that
 * blows up against a table they do not have.
 */
it('skips widening when the social table is absent', function () {
    Schema::drop('passwordless_social_accounts');

    $migration = avatarWideningMigration();

    $migration->up();
    $migration->down();

    expect(Schema::hasTable('passwordless_social_accounts'))->toBeFalse();
});
