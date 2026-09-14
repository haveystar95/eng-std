<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * THE LEARNER'S GENDER, WHEN THEY SAY IT (наряд GEN-2a): `female` | `male`, null = not said.
     *
     * Read by the plan when a lesson is written — the prompt's LEARNER_GENDER shapes the grammar of the
     * learner's own lines in their language («я работала» / «я работал»); null is sent as «unknown»,
     * and the lesson then prefers constructions without gender. Set through `PUT /profile`.
     *
     * Nullable, no default: a metadata-only ALTER, nothing rewritten. No index: read with its row.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE profiles ADD COLUMN gender varchar(6) NULL');
        DB::statement("ALTER TABLE profiles ADD CONSTRAINT profiles_gender_check CHECK (gender IS NULL OR gender IN ('female','male'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE profiles DROP CONSTRAINT IF EXISTS profiles_gender_check');
        DB::statement('ALTER TABLE profiles DROP COLUMN IF EXISTS gender');
    }
};
