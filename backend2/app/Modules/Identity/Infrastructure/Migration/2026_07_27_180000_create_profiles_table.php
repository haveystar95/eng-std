<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('user_id', 26)->unique();
            $table->string('native_language', 5)->default('ru');
            $table->string('target_language', 5)->default('en');
            $table->string('cefr_level', 4)->default('B1');            // self-assessed CEFR
            $table->unsignedSmallInteger('daily_goal')->default(20);   // new terms/day
            $table->timestampsTz();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
