<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A LINE HAS NO EXAMPLE — the rows that already exist, removed.
     *
     * «Пример есть только у `words` и `chunks`» is the contract of a day-scene (канон §7,
     * {@see \App\Modules\Generation\Domain\ValueObject\PlanShelf::wantsExample()}), and the day
     * writer has always honoured it. The echo-repair chain did not: its test for a broken term is
     * «no example at all», so every line of every plan day looked like a repair job and bought
     * itself a sentence, one model call each ({@see \App\Modules\Generation\Domain\Service\ExampleAdmission},
     * which is the code side of this).
     *
     * What that produced, on the owner's live day 1 of 02.09 (`ex-regen.v2`, thirteen calls in
     * fourteen seconds): «I see, without utilities.» — a card of the scene — taught by «When the
     * power went out, I realized that I see, without utilities, life becomes…». The card word for
     * word, padded into a sentence that means nothing, shown to the learner on the intro.
     *
     * ## Reversible, and that is why it is three tables and not one DELETE
     *
     * The rows are COPIED into companion tables before they are removed, and `down()` puts them
     * back — the sentences were paid for, and a migration that could only be run one way against
     * the owner's own database is not something to run at all. Both dependants come with them:
     * `example_translations` and `example_distractors` are `cascadeOnDelete` on the example id, so
     * they would vanish silently and there would be nothing to restore.
     *
     * `CREATE TABLE … AS SELECT` rather than a hand-written column list on purpose: a backup that
     * enumerates columns is a backup that loses the column somebody adds next month.
     */
    public function up(): void
    {
        foreach (array_values(self::TABLES) as $backup) {
            Schema::dropIfExists($backup);
        }

        DB::statement(<<<'SQL'
            CREATE TABLE term_examples_line_backup AS
            SELECT e.* FROM term_examples e
            JOIN terms t ON t.id = e.term_id
            WHERE t.kind = 'line'
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE example_translations_line_backup AS
            SELECT x.* FROM example_translations x
            WHERE x.term_example_id IN (SELECT id FROM term_examples_line_backup)
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE example_distractors_line_backup AS
            SELECT d.* FROM example_distractors d
            WHERE d.example_id IN (SELECT id FROM term_examples_line_backup)
        SQL);

        // The two dependants go with the row anyway (cascade); deleting the parent is the whole
        // statement, and the copies above are what makes it undoable.
        DB::statement(<<<'SQL'
            DELETE FROM term_examples e
            USING terms t
            WHERE t.id = e.term_id AND t.kind = 'line'
        SQL);
    }

    public function down(): void
    {
        // Parents first, then what hangs off them — the foreign keys are the order.
        foreach (self::TABLES as $table => $backup) {
            if (Schema::hasTable($backup)) {
                DB::statement("INSERT INTO {$table} SELECT * FROM {$backup} ON CONFLICT DO NOTHING");
            }
        }

        foreach (array_values(self::TABLES) as $backup) {
            Schema::dropIfExists($backup);
        }
    }

    /** table => its backup, in restore order. */
    private const TABLES = [
        'term_examples' => 'term_examples_line_backup',
        'example_translations' => 'example_translations_line_backup',
        'example_distractors' => 'example_distractors_line_backup',
    ];
};
