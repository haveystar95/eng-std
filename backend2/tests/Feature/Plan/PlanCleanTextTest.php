<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

/**
 * `plan:clean-text` (наряд LANG-1b §6): «команда plan:clean-text --dry-run → --apply по бою (с бэкапом): число исправленных
 * строк в отчёт». The plan is built by the fake model, which — like every answer stored before the наряд — skips the
 * adapter that now reads the model's text through {@see App\Modules\Plan\Domain\Service\ModelText}: the live day's title
 * «Опы\u{0004}т и навыки», a soft hyphen in a phrase of the lesson, a zero-width space in a dealt card.
 */

/** @return array{0: string, 1: string} the plan and its first scene, the scene titled as the live day was */
function pctDirtyPlan(object $ctx): array
{
    $fake = new FakePlanModel(
        plan: static function ($request): array {
            $p = FakePlanModel::planPayload($request);
            $p['scenes'][0]['title_native'] = "Опы\u{0004}т и навыки";

            return $p;
        },
        lesson: static function ($request): array {
            $p = planCleanLesson($request);
            $p['dialogue'][0]['messages'][0]['text_native'] = "Где бо\u{00AD}лит: вверху или внизу спины?";

            return $p;
        },
    );
    app()->instance(PlanModelPort::class, $fake);
    [, $token] = planLearner();
    $id = planCreate($ctx, $token, ['days_total' => 1])['id'];
    $scene = (string) DB::table('plan_scenes')->where('plan_id', $id)->orderBy('order')->value('id');
    // The photo vendor's author: a Persian name keeps its non-joiner — not the model's text, never touched.
    DB::table('plan_scenes')->where('id', $scene)->update(['image_author' => "Sed\u{200C} \"Creatives\" Sardar"]);

    return [$id, $scene];
}

// CATCHES a dry run that writes, an apply that leaves a dirty text or touches the vendor's author name, a JSON column not read
// (the lesson as the model wrote it), a count that says nothing, and a second run that finds something again.
it('prints what it would clean, cleans it on --apply, leaves the vendor\'s names alone, and finds nothing the second time', function () {
    [, $scene] = pctDirtyPlan($this);

    Artisan::call('plan:clean-text', ['--dry-run' => true]);
    $dry = Artisan::output();
    expect($dry)->toContain('[dry-run] plan_scenes · '.$scene.' · title_native: «Опы⟨U+0004⟩т и навыки» → «Опыт и навыки»')
        ->and($dry)->toContain('lesson_json')
        ->and($dry)->toContain('[dry-run] Would fix rows: ')
        ->and(DB::table('plan_scenes')->where('id', $scene)->value('title_native'))->toBe("Опы\u{0004}т и навыки");

    Artisan::call('plan:clean-text', ['--apply' => true]);
    $applied = Artisan::output();
    $row = DB::table('plan_scenes')->where('id', $scene)->first();
    $lesson = json_decode((string) $row->lesson_json, true);

    expect($applied)->toMatch('/Fixed rows: [1-9]\d* \(plans 0, plan_scenes 1, plan_terms \d+, day_cards \d+\) · fields: \d+/')
        ->and($row->title_native)->toBe('Опыт и навыки')
        ->and($lesson['dialogue'][0]['messages'][0]['text_native'])->toBe('Где болит: вверху или внизу спины?')
        ->and($row->image_author)->toBe("Sed\u{200C} \"Creatives\" Sardar");

    Artisan::call('plan:clean-text', ['--apply' => true]);
    expect(Artisan::output())->toContain('Fixed rows: 0 (plans 0, plan_scenes 0, plan_terms 0, day_cards 0) · fields: 0');
});

it('refuses --dry-run and --apply together', function () {
    expect(Artisan::call('plan:clean-text', ['--dry-run' => true, '--apply' => true]))->toBe(1);
});

/**
 * The first reading of a JSON value (a string under `pronunciation_native`, at any depth) set to `$reading`, and the
 * `text_native` beside it to `$native`; false when the value holds no reading.
 *
 * @param  array<mixed>  $value
 */
function pctFirstReading(array &$value, string $reading, string $native): bool
{
    if (isset($value['pronunciation_native']) && is_string($value['pronunciation_native'])) {
        $value['pronunciation_native'] = $reading;
        $value['text_native'] = $native;

        return true;
    }
    foreach ($value as &$item) {
        if (is_array($item) && pctFirstReading($item, $reading, $native)) {
            return true;
        }
    }

    return false;
}

// Наряд LANG-1b, последнее: «plan:clean-text распространить на поля чтения (pronunciation_native) в plan_terms и day_cards —
// те же замены чужих кириллиц и латинских двойников, что в парсере». The owner's day 1 of «Собеседование» showed «а аҗута́»
// after §10.3 was deployed: the parser mends a lesson as it loads it, the window and the cards read `plan_terms`. CATCHES a
// reading field left unread (a phrase's frame reading, a filler's reading in the slot, a card's reading at any depth), a rule
// other than the parser's, a line or a translation «mended» as if it were a reading, a reading of Latin letters touched, and
// a second run that finds something again.
it('puts the stored readings of words, phrases and dealt cards in the letters of the readings, by the parser\'s rule, and nothing else', function () {
    $fake = new FakePlanModel;
    app()->instance(PlanModelPort::class, $fake);
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 1])['id'];
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();
    planOpenDay($this, $token, $id, 1);
    $scene = (string) DB::table('plan_scenes')->where('plan_id', $id)->value('id');

    // Stored as the parser read readings before наряд LANG-1b §10.3 — and a Latin twin, as before LANG-1.
    $word = DB::table('plan_terms')->where('scene_id', $scene)->where('kind', 'word')->orderBy('ref')->first();
    DB::table('plan_terms')->where('id', $word->id)->update(['pronunciation_native' => "а аҗута\u{0301}", 'text_native' => 'помогатa']);
    $phrase = DB::table('plan_terms')->where('scene_id', $scene)->where('kind', 'phrase')->whereNotNull('slot')->orderBy('ref')->first();
    $slot = json_decode((string) $phrase->slot, true);
    $slot['fillers'][0]['pronunciation_native'] = 'шaрп';
    $slot['fillers'][0]['native'] = 'острaя';
    DB::table('plan_terms')->where('id', $phrase->id)->update([
        'pronunciation_native' => "Ам аҗута\u{0301}т клие\u{0301}нций.",
        'frame_pronunciation_native' => "Ам аҗута\u{0301}т ___ .",
        'slot' => json_encode($slot, JSON_UNESCAPED_UNICODE),
    ]);
    // A learner who reads Latin letters: a reading with no Cyrillic in it is theirs, whatever its letters.
    $latin = DB::table('plan_terms')->where('scene_id', $scene)->where('kind', 'word')->orderBy('ref')->skip(1)->first();
    DB::table('plan_terms')->where('id', $latin->id)->update(['pronunciation_native' => 'łajk tu kam']);
    $card = null;
    foreach (DB::table('day_cards')->orderBy('id')->get() as $row) {
        $payload = json_decode((string) $row->payload, true);
        if (is_array($payload) && pctFirstReading($payload, 'шaрп аҗута', 'острaя')) {
            DB::table('day_cards')->where('id', $row->id)->update(['payload' => json_encode($payload, JSON_UNESCAPED_UNICODE)]);
            $card = (string) $row->id;
            break;
        }
    }
    expect($card)->not->toBeNull();

    Artisan::call('plan:clean-text', ['--dry-run' => true]);
    $dry = Artisan::output();
    expect($dry)->toContain("[dry-run] plan_terms · {$word->id} · pronunciation_native: «а аҗута\u{0301}» → «а ажута\u{0301}»")
        ->and($dry)->toContain("[dry-run] plan_terms · {$phrase->id} · frame_pronunciation_native: «Ам аҗута\u{0301}т ___ .» → «Ам ажута\u{0301}т ___ .»")
        ->and($dry)->toContain("[dry-run] day_cards · {$card} · payload: «шaрп аҗута» → «шарп ажута»")
        ->and($dry)->toContain('[dry-run] Would fix rows: 3 (plans 0, plan_scenes 0, plan_terms 2, day_cards 1) · fields: 5')
        ->and(DB::table('plan_terms')->where('id', $word->id)->value('pronunciation_native'))->toBe("а аҗута\u{0301}");

    Artisan::call('plan:clean-text', ['--apply' => true]);
    $words = DB::table('plan_terms')->whereIn('id', [$word->id, $phrase->id, $latin->id])->get()->keyBy('id');
    $fixedSlot = json_decode((string) $words[$phrase->id]->slot, true);
    $fixedCard = json_decode((string) DB::table('day_cards')->where('id', $card)->value('payload'), true);
    $cardText = json_encode($fixedCard, JSON_UNESCAPED_UNICODE);

    expect(Artisan::output())->toContain('Fixed rows: 3 (plans 0, plan_scenes 0, plan_terms 2, day_cards 1) · fields: 5')
        ->and($words[$word->id]->pronunciation_native)->toBe("а ажута\u{0301}")
        ->and($words[$phrase->id]->pronunciation_native)->toBe("Ам ажута\u{0301}т клие\u{0301}нций.")
        ->and($words[$phrase->id]->frame_pronunciation_native)->toBe("Ам ажута\u{0301}т ___ .")
        ->and($fixedSlot['fillers'][0]['pronunciation_native'])->toBe('шарп')
        ->and($cardText)->toContain('"pronunciation_native":"шарп ажута"')
        // Not readings: the model's translations stay as written, the Latin twin in them too; a Latin reading is its own.
        ->and($words[$word->id]->text_native)->toBe('помогатa')
        ->and($fixedSlot['fillers'][0]['native'])->toBe('острaя')
        ->and($cardText)->toContain('"text_native":"острaя"')
        ->and($words[$latin->id]->pronunciation_native)->toBe('łajk tu kam');

    Artisan::call('plan:clean-text', ['--apply' => true]);
    expect(Artisan::output())->toContain('Fixed rows: 0 (plans 0, plan_scenes 0, plan_terms 0, day_cards 0) · fields: 0');
});
