<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * THREE FACTS A v0.2 PLAN DAY KNOWS ABOUT ITS CARDS, and one of them changes what the trainer
     * deals.
     *
     * ## `terms.kind` — line, word or connector
     *
     * `is_line` answered a two-way question and v0.2 asks a three-way one: P2 returns `phrases`,
     * `words` and `chunks`, and the third is not a rounding of the other two. A connector («deal
     * with», «be in charge of») behaves like a word in a session — it goes into a slot — and it
     * behaves like a phrase lexically, which is exactly the collision `type` + `is_line` was split
     * to survive. What it needs is its own answer to «what does this DO in the day», because the
     * stage checklists差 by it: a line never gets typing or dictation, a word does, and a connector
     * takes the word's ladder with the gap always cut in the day's own frame.
     *
     * `is_line` stays and is not derived from this: it is the older, coarser fact, it is written
     * for every term the plan touches, and a term that came from an ordinary collection has a
     * `kind` of NULL and an `is_line` of false — «not known to be a line», which is not the same
     * as «known to be a word».
     *
     * ## `terms.frame` — the line with its slot
     *
     * «I worked on ___». The line's `text` is the same sentence with a real word of that day in
     * the hole. This is what makes eight lines and six words twenty sentences instead of eight
     * memorised ones, and it is what the cloze card cuts its gap from — the day's own frame rather
     * than whatever the general example happens to contain.
     *
     * NULL on a formula («Nice to meet you»), which has no slot, and on every term that never came
     * from a plan day.
     *
     * ## `terms.speaker` — whose turn it is
     *
     * `learner` or `role`. The interlocutor's lines are quoted verbatim from the scene's
     * `opening_lines` and exist so the learner can RECOGNISE them; the learner's own lines exist so
     * they can say them. CONV-1 needs the distinction to run a conversation; the session needs it
     * to know which lines it may ask to be produced.
     *
     * ## Why on `terms` and not on a plan-side table
     *
     * The same argument `is_line` and `difficulty_score` were added under, one level further: these
     * are written when a term is CREATED for a day and re-written every time a plan day imports it,
     * so what is stored is the frame of the day that is being studied. Terms are globally
     * deduplicated and a term is introduced on exactly one day of a plan
     * ({@see \App\Modules\Generation\Domain\Service\PlanCoherenceValidator}), so within a plan
     * there is no second frame to disagree with. Across plans the last import wins, which is the
     * honest behaviour: the frame belongs to the day being taught now.
     */
    public function up(): void
    {
        Schema::table('terms', function (Blueprint $table): void {
            $table->string('kind', 8)->nullable()->after('is_line');
            $table->text('frame')->nullable()->after('kind');
            $table->string('speaker', 8)->nullable()->after('frame');
        });

        DB::statement("ALTER TABLE terms ADD CONSTRAINT terms_kind_check CHECK (kind IS NULL OR kind IN ('line','word','chunk'))");
        DB::statement("ALTER TABLE terms ADD CONSTRAINT terms_speaker_check CHECK (speaker IS NULL OR speaker IN ('learner','role'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE terms DROP CONSTRAINT IF EXISTS terms_speaker_check');
        DB::statement('ALTER TABLE terms DROP CONSTRAINT IF EXISTS terms_kind_check');

        Schema::table('terms', function (Blueprint $table): void {
            $table->dropColumn(['kind', 'frame', 'speaker']);
        });
    }
};
