<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keep the (truncated) raw model response on a request so a bad generation can be diagnosed
 * without re-spending on the model. Written on every attempt, so it survives both success and
 * a validation failure — the case where we paid for tokens but shipped nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generation_requests', function (Blueprint $table): void {
            $table->text('raw_response')->nullable()->after('error');
        });
    }

    public function down(): void
    {
        Schema::table('generation_requests', function (Blueprint $table): void {
            $table->dropColumn('raw_response');
        });
    }
};
