<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Exception\ModelAnswerOffSchema;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Lesson\OptionShuffle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * THE OPTIONS OF A LESSON WRITTEN IN ONE CALL STAND WHERE ITS LEARNER SAW THEM (наряд GEN-4, 3.7; DECISIONS п. 452). A
     * lesson of `lesson_day` — a scene with a lesson and no skeleton — is stored with its options as the model wrote them, the
     * right one first nine times out of ten, and until GEN-4 the served lesson shuffled them at every reading, seeded by the
     * scene and the question's address. The served lesson moves nothing any more (the two stages shuffle once, when the day is
     * built), so each such lesson is shuffled here once, with the same seeds and the same shuffle
     * ({@see OptionShuffle::lesson()}): a day dealt from it after GEN-4 is dealt as it was before. The row is read and written
     * as every save of a scene reads and writes it (`LessonParser::parse()` → `toArray()`); a row that does not parse — no
     * reader can serve it either — is left as it is and named in the log. A lesson with a skeleton is the two stages',
     * shuffled at its build, and is not touched.
     *
     * Once only: shuffled twice, the options would move again. A lesson written in one call AFTER this — a worker still on
     * the code of before GEN-4 — is not shuffled by it: the queue is stopped for the deploy (the handoff says how).
     */
    public function up(): void
    {
        $parser = new LessonParser;
        DB::table('plan_scenes')->whereNotNull('lesson_json')->whereNull('skeleton_json')
            ->chunkById(100, static function ($rows) use ($parser): void {
                foreach ($rows as $row) {
                    $stored = json_decode((string) $row->lesson_json, true);
                    if (! is_array($stored)) {
                        continue;
                    }
                    try {
                        $lesson = OptionShuffle::lesson($parser->parse($stored), (string) $row->id);
                    } catch (ModelAnswerOffSchema $e) {
                        Log::warning('A stored lesson of one call does not parse; its options are left as stored', ['scene_id' => $row->id, 'error' => $e->getMessage()]);

                        continue;
                    }
                    DB::table('plan_scenes')->where('id', $row->id)
                        ->update(['lesson_json' => json_encode($lesson->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
                }
            });
    }

    /** One way: the model's own order is kept nowhere once shuffled, and nothing reads it. */
    public function down(): void {}
};
