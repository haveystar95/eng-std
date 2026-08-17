<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pexels imagery on a term (A3). All nullable, all additive — no backfill, no constraint.
 *
 * - image_api_prompt is the model's short image-search query for this term, set at term creation
 *   (v4 prompt). It is server-internal: it drives AttachImagesJob and is never shipped to the client.
 * - image_url + image_author + image_author_url are filled asynchronously once a stock photo is found;
 *   Pexels licence requires crediting the photographer with a link, so both attribution fields ride
 *   alongside the url. They stay null when no photo matches (a valid, placeholder-on-client state).
 *
 * The image lives on the term, not the collection item, because terms are global and deduplicated —
 * one search per term, shared by every collection that references it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terms', function (Blueprint $table): void {
            $table->text('image_url')->nullable();
            $table->text('image_api_prompt')->nullable();
            $table->text('image_author')->nullable();
            $table->text('image_author_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('terms', function (Blueprint $table): void {
            $table->dropColumn(['image_url', 'image_api_prompt', 'image_author', 'image_author_url']);
        });
    }
};
