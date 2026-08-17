<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    // Whether this answer was a triage "known" verification check (the term was `known` when
    // answered). A fact about the answer's context at the moment it happened — the term's state
    // changes right after, so it cannot be reconstructed later, only recorded now. Feeds the
    // verification fail-rate metric.
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->boolean('is_verification')->default(false)->after('is_practice');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn('is_verification');
        });
    }
};
