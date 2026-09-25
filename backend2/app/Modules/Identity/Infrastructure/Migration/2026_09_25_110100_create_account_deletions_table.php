<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ONE LINE PER DELETED ACCOUNT, FOR THE COUNT OF THEM (наряд ACC-1 §1): when, and how many plans the learner had —
     * and not who. `user_hash` is an HMAC of the account's id under the application key (`CrossModuleAccountEraser`):
     * a count that tells two deletions apart and cannot be walked back to the id from this table.
     *
     * Access path: the statistics read by date (`account_deletions_deleted_idx`); the hash is unique — one account is
     * deleted once.
     */
    public function up(): void
    {
        Schema::create('account_deletions', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('user_hash', 64)->unique('account_deletions_user_hash_uidx');
            $table->unsignedInteger('plans_count');
            $table->timestampTz('deleted_at');

            $table->index('deleted_at', 'account_deletions_deleted_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deletions');
    }
};
