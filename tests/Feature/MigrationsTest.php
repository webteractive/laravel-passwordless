<?php

use Illuminate\Support\Facades\Schema;

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
