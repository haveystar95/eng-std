<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * WHICH TARGET A LINE OF THE ROLE OPENS THE DOOR TO (наряд FIX-3 §7): the id (`<scene>:<ref>`) of the construction the
     * learner could say next in answer to it, as the role named it. The talk leads to its targets one by one — each door
     * opened once — and reads the doors already opened off the role's own lines; the hint of the next move is the target
     * the last line opened. Null on the learner's lines, on a line that opens nothing, and on every line written before.
     *
     * Nullable, no default: a metadata-only ALTER on an append-only journal — rows are written once and never updated.
     * No index: read with its conversation, by the index the journal already has.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE conversation_turns ADD COLUMN opens_target varchar(64) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE conversation_turns DROP COLUMN IF EXISTS opens_target');
    }
};
