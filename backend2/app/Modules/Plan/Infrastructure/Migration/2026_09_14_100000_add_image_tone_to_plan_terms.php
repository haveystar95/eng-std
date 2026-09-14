<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * THE TONE OF A WORD'S PHOTO SLOT (DAY-UI-2): the vendor's average colour of the word's photo,
     * written in the same conditional UPDATE as the photo — and, for a word the whole search ladder
     * found no photo for, the tone the card is painted with instead, which is also the mark that the
     * ladder was asked (the photo job does not ask again after every lesson).
     *
     * Nullable, no default: a metadata-only ALTER, no table rewrite. Null = never asked; the day
     * window then paints the scene's tone. The CHECK holds the format `Image::normalTone` writes. No
     * index: the column is read with its row, never filtered on.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE plan_terms ADD COLUMN image_tone varchar(7) NULL');
        DB::statement("ALTER TABLE plan_terms ADD CONSTRAINT plan_terms_image_tone_check CHECK (image_tone IS NULL OR image_tone ~ '^#[0-9A-F]{6}$')");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE plan_terms DROP CONSTRAINT IF EXISTS plan_terms_image_tone_check');
        DB::statement('ALTER TABLE plan_terms DROP COLUMN IF EXISTS image_tone');
    }
};
