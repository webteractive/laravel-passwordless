<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A user table that deliberately breaks two common assumptions: the primary key
 * is a uuid rather than `id`, and there is no `name` column. Used to prove the
 * dev-login picker reads the key from the model and treats `name` as optional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uuid_users', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('email')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uuid_users');
    }
};
