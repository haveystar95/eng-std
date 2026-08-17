<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Back-office operators. A table entirely separate from `users`: app users can never reach
        // the admin panel, and an admin is not an app user. Sanctum tokens are minted against this
        // table (the `admin` guard's provider), so a user token can't authenticate an admin route.
        Schema::create('admins', function (Blueprint $table): void {
            $table->char('id', 26)->primary();            // server ULID (matches HasUlids)
            $table->string('email')->unique();
            $table->string('password');                    // bcrypt hash
            $table->string('name');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
