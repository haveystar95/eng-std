<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * «СВОЁ ОКНО» СТАЛО ПОСЛЕДНИМ КРУГОМ «СКАЖИ ЦЕЛИКОМ» (наряд FIX-2, п. 5).
     *
     * `phrase_own_slot` is no longer a kind of the registry: a frame with a window is now said aloud ONE way at every
     * level — «Скажи целиком» (`phrase_other_slot`), in rounds over the window's values and ending with the learner's
     * own. The enum that reads a dealt card no longer has the case, so the rows already dealt as `phrase_own_slot`
     * would refuse to hydrate and a day room holding one would 500.
     *
     * Their `kind` is rewritten, and only their `kind`: all of them are in days the owner has already walked or is
     * walking (QA plans of the second account, `docs/research/fix-2/README.md` §1.5), the payload beside them is of
     * the contract this наряд replaces, and the card of a walked day is history — its result, its attempts and its
     * unit are what the room reads, not the shape of the trainer it was answered on.
     *
     * NO SCHEMA CHANGES. The CHECK on `kind` already allows `phrase_other_slot`; `phrase_own_slot` stays allowed by
     * the database and written by nothing, which is what a value nobody deals looks like from here.
     *
     * `down()` cannot tell the two apart any more — the rows it would split are indistinguishable once merged — so it
     * leaves them merged. That is the honest direction: the rollback this migration needs is the code's, not the
     * data's.
     */
    public function up(): void
    {
        $merged = DB::table('day_cards')->where('kind', 'phrase_own_slot')->update(['kind' => 'phrase_other_slot']);

        Log::info('plan: own-slot cards merged into say-whole', ['cards' => $merged]);
    }

    public function down(): void
    {
        // Left merged on purpose: see the class docblock.
    }
};
