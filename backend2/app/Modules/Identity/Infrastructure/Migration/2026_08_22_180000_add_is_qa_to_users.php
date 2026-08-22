<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The QA mark on an account.
 *
 * Additive and defaulted to false, so every account that exists today stays exactly what it was.
 * The column is not a preference and not a tier: it is the ONE fact that unlocks the destructive
 * QA tooling (`qa:time-travel`, `qa:reset`) and the dev sign-in. Everything on that side asks this
 * column first and refuses when it is false — which is why the DEFAULT matters more than the
 * column: an account nobody deliberately marked can never be time-travelled or wiped.
 *
 * No index: this is looked up by primary key or by email, never scanned by the flag, and the QA
 * rows are a handful against a fleet that is one person.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_qa')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_qa');
        });
    }
};
