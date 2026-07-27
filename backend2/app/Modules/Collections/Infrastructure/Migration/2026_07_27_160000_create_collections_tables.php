<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('owner_id', 26)->nullable();
            $table->string('type', 16);          // system | shared | custom
            $table->text('slug')->nullable();
            $table->text('title');
            $table->text('description')->nullable();
            $table->text('topic')->nullable();
            $table->string('source_lang', 5);
            $table->string('target_lang', 5);
            $table->string('visibility', 16);    // private | link | public
            $table->string('source', 16);        // curated | ai | user
            $table->char('generation_request_id', 26)->nullable();
            $table->integer('items_count')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement("ALTER TABLE collections ADD CONSTRAINT collections_type_check CHECK (type IN ('system','shared','custom'))");
        DB::statement("ALTER TABLE collections ADD CONSTRAINT collections_visibility_check CHECK (visibility IN ('private','link','public'))");
        DB::statement("ALTER TABLE collections ADD CONSTRAINT collections_source_check CHECK (source IN ('curated','ai','user'))");
        DB::statement('CREATE INDEX collections_owner_idx ON collections (owner_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX collections_type_visibility_idx ON collections (type, visibility) WHERE deleted_at IS NULL');

        Schema::create('collection_items', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('collection_id', 26);
            $table->char('term_id', 26);
            $table->integer('position');
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->foreign('collection_id')->references('id')->on('collections')->cascadeOnDelete();
            $table->foreign('term_id')->references('id')->on('terms')->restrictOnDelete();
            $table->unique(['collection_id', 'term_id'], 'collection_items_uidx');
            $table->index(['collection_id', 'position'], 'collection_items_order_idx');
            $table->index('term_id', 'collection_items_term_idx');
        });

        Schema::create('user_collections', function (Blueprint $table) {
            $table->char('user_id', 26);
            $table->char('collection_id', 26);
            $table->timestampTz('added_at');
            $table->boolean('is_pinned')->default(false);
            $table->jsonb('settings')->nullable();

            $table->primary(['user_id', 'collection_id']);
            $table->foreign('collection_id')->references('id')->on('collections')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_collections');
        Schema::dropIfExists('collection_items');
        Schema::dropIfExists('collections');
    }
};
