<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('words', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Normalized (lowercased, trimmed) key used for dedup.
            $table->string('term_key');
            $table->string('term');
            $table->string('translation');
            $table->string('transcription')->nullable();
            $table->text('example')->nullable();
            $table->string('cefr_level')->nullable();
            $table->timestamps();

            // One shared word per user; reused across collections.
            $table->unique(['user_id', 'term_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('words');
    }
};
