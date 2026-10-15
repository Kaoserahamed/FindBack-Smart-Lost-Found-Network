<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widen claims.answers so the values can be encrypted at rest.
 *
 * Claim::answers now uses the `encrypted:array` cast. Laravel's ciphertext is
 * base64 text, so the column moves from jsonb to text - keeping it as jsonb
 * would reject the payload.
 *
 * IMPORTANT: existing plaintext jsonb rows are NOT re-encrypted by this
 * migration. Run `php artisan claims:encrypt-answers` afterwards to migrate
 * legacy data, otherwise reading an old row raises a DecryptException.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->text('answers')->change();
        });
    }

    public function down(): void
    {
        // PostgreSQL cannot implicitly cast ciphertext back to jsonb, so rows
        // that are not valid JSON are reset to an empty object instead of
        // aborting the rollback.
        DB::statement("UPDATE claims SET answers = '{}' WHERE answers IS NULL OR left(answers, 1) <> '{'");
        DB::statement('ALTER TABLE claims ALTER COLUMN answers TYPE jsonb USING answers::jsonb');
    }
};
