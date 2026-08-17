<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Record how many items a succeeded generation actually delivered. It can be fewer than the
 * requested `size` (the model under-produces and even one avoid-list top-up doesn't fully close
 * the gap) — the client shows "13 из 15" honestly instead of pretending it got the full set.
 * Nullable: only a succeeded request has a delivered count; pending/running/failed leave it null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generation_requests', function (Blueprint $table): void {
            $table->integer('delivered_count')->nullable()->after('size');
        });
    }

    public function down(): void
    {
        Schema::table('generation_requests', function (Blueprint $table): void {
            $table->dropColumn('delivered_count');
        });
    }
};
