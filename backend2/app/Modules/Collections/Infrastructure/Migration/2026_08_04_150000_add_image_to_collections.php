<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pexels cover image on a collection (A3). All nullable, all additive.
 *
 * Symmetric with terms: image_api_prompt is the model's cover-image search query (from the v4
 * prompt's collection_image_prompt), server-internal; image_url + attribution are filled
 * asynchronously by AttachImagesJob and stay null when no photo matches.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table): void {
            $table->text('image_url')->nullable();
            $table->text('image_api_prompt')->nullable();
            $table->text('image_author')->nullable();
            $table->text('image_author_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table): void {
            $table->dropColumn(['image_url', 'image_api_prompt', 'image_author', 'image_author_url']);
        });
    }
};
