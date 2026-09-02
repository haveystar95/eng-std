<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * THE SHELF, THE TIER, THE SKILL AND THE NUMBER — what a v0.4 day knows about its cards.
     *
     * A day of a plan is a SCENE now (`docs/plan-model.md` §2), and a scene has shelves: «Тебе
     * скажут», «Ты ответишь», «Ты спросишь», слова, связки, цифры. Four columns, and each one
     * answers a question the existing three could not:
     *
     * ## `shelf` — because `kind` cannot tell «say» from «ask», or either from «hear»
     *
     * All three are `kind = line`. Two of them the learner says and one they will never say; two of
     * them get different captions on the same screen. The shelf is what the session labels a seam
     * with and what the warm-up finds its cards by, and deriving it from `kind` + `speaker` would be
     * a rule three readers had to agree on — the exact shape of the defect this project keeps
     * paying for (Д-8, Д-33).
     *
     * `rescue` is a shelf too, and it is the SERVER's: the five phrases of the language pack
     * (канон §5), written into day 1 and dealt in every warm-up after it. It is the one shelf no
     * model ever writes.
     *
     * ## `tier` — stored, though it is derived, because deriving it twice is how it drifts
     *
     * `speak` walks A → B → C; `understand` is two touches and stops at B (канон §3). The value is
     * a pure function of the shelf ({@see \App\Modules\Generation\Domain\ValueObject\PlanShelf::tier()}),
     * and it is written down anyway: the checklist, the card assembler and the day screen all ask
     * «what may this card be asked of», and a rule recomputed in three places is a rule that will
     * eventually be recomputed differently in one of them.
     *
     * ## `skill_ref` — «почему я это учу», as a string a gate can check
     *
     * The id of the ONE skill of the scene this card serves (канон §8). It is what makes «карточка
     * без умения» a mechanical failure rather than a judgement.
     *
     * ## `number_value` — the digits nothing else can see
     *
     * A `numbers` card plays a line and the learner types the number. The digits never appear on
     * the screen, so a value that disagrees with the line it is heard in is invisible to every
     * reader and to the learner, right up until the moment the card marks them wrong.
     *
     * ## THE BACKFILL: no plan already running loses its cards
     *
     * Every stored plan term gets the shelf its old shape implies — a role line is `hear`, every
     * other line is `say`, a word is `words`, a connector is `chunks` — and the tier that follows.
     * Nothing is guessed about ordinary vocabulary: a term with no `kind` never came from a plan
     * day and keeps four nulls. So a plan the owner is halfway through goes on dealing exactly the
     * cards it dealt yesterday, under captions it can now be given.
     *
     * No index: every one of these is read together with the term row the session already holds,
     * and the one query that filters by shelf (the warm-up) does it over a single day's collection.
     */
    public function up(): void
    {
        Schema::table('terms', function (Blueprint $table): void {
            $table->string('shelf', 8)->nullable()->after('kind');
            $table->string('tier', 10)->nullable()->after('shelf');
            $table->string('skill_ref', 32)->nullable()->after('tier');
            $table->string('number_value', 32)->nullable()->after('skill_ref');
        });

        // `number` is a fourth kind — a price, a date, a house number, heard inside a line.
        DB::statement('ALTER TABLE terms DROP CONSTRAINT IF EXISTS terms_kind_check');
        DB::statement(
            "ALTER TABLE terms ADD CONSTRAINT terms_kind_check "
            . "CHECK (kind IS NULL OR kind IN ('line','word','chunk','number'))"
        );
        DB::statement(
            "ALTER TABLE terms ADD CONSTRAINT terms_shelf_check "
            . "CHECK (shelf IS NULL OR shelf IN ('hear','say','ask','words','chunks','numbers','rescue'))"
        );
        DB::statement(
            "ALTER TABLE terms ADD CONSTRAINT terms_tier_check "
            . "CHECK (tier IS NULL OR tier IN ('speak','understand'))"
        );

        // The days that already exist, given the shelves their shape implies.
        DB::statement(
            "UPDATE terms SET shelf = 'hear', tier = 'understand', updated_at = now() "
            . "WHERE kind = 'line' AND speaker = 'role'"
        );
        DB::statement(
            "UPDATE terms SET shelf = 'say', tier = 'speak', updated_at = now() "
            . "WHERE kind = 'line' AND (speaker IS NULL OR speaker = 'learner')"
        );
        DB::statement("UPDATE terms SET shelf = 'words', tier = 'speak', updated_at = now() WHERE kind = 'word'");
        DB::statement("UPDATE terms SET shelf = 'chunks', tier = 'speak', updated_at = now() WHERE kind = 'chunk'");
    }

    public function down(): void
    {
        // A NUMBER STOPS BEING ONE rather than being deleted. The rollback target has no `number`
        // kind and its CHECK would refuse the row; deleting the term instead would take the
        // learner's answers with it, and a card that reads as an ordinary line is a smaller loss
        // than a review log with holes in it.
        DB::statement("UPDATE terms SET kind = NULL WHERE kind = 'number'");

        DB::statement('ALTER TABLE terms DROP CONSTRAINT IF EXISTS terms_tier_check');
        DB::statement('ALTER TABLE terms DROP CONSTRAINT IF EXISTS terms_shelf_check');
        DB::statement('ALTER TABLE terms DROP CONSTRAINT IF EXISTS terms_kind_check');
        DB::statement(
            "ALTER TABLE terms ADD CONSTRAINT terms_kind_check "
            . "CHECK (kind IS NULL OR kind IN ('line','word','chunk'))"
        );

        Schema::table('terms', function (Blueprint $table): void {
            $table->dropColumn(['shelf', 'tier', 'skill_ref', 'number_value']);
        });
    }
};
