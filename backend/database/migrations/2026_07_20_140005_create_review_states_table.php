<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('review_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('word_id')->constrained()->cascadeOnDelete();

            // FSRS memory state
            $table->double('stability')->default(0);
            $table->double('difficulty')->default(0);
            $table->unsignedInteger('reps')->default(0);
            $table->unsignedInteger('lapses')->default(0);
            $table->string('state')->default('new'); // new | learning | review | relearning
            $table->timestamp('last_reviewed_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->unsignedTinyInteger('last_rating')->nullable();

            $table->timestamps();
            $table->unique(['user_id', 'word_id']);
            $table->index(['user_id', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_states');
    }
};
