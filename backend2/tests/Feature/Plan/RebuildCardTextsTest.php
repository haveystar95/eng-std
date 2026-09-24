<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * THE CARDS OF A DAY DEALT WITH «…3 p.m..» (наряд FIX-4 §6): `plan:rebuild-card-texts` writes the frame-and-filler sentence
 * where a card doubled an abbreviation's dot — nothing else, nothing bought, dry-run unless `--apply`, and once.
 */
uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

/** The fake's clean lesson with «He will rest ___.» said with «until 3 p.m.» — as the model wrote it, dot doubled. */
function rctLesson(LessonRequest $request): array
{
    $payload = planCleanLesson($request);
    foreach ($payload['phrases'] as $i => $phrase) {
        if ($phrase['id'] === 'p5') {
            $payload['phrases'][$i]['slot']['fillers'][0]['target'] = 'until 3 p.m.';
        }
    }
    foreach ($payload['dialogue'] as $i => $exchange) {
        foreach ($exchange['messages'] as $m => $message) {
            if (($message['phrase_id'] ?? null) === 'p5') {
                $payload['dialogue'][$i]['messages'][$m]['filler'] = 'until 3 p.m.';
                $payload['dialogue'][$i]['messages'][$m]['text_target'] = 'Okay, he will rest until 3 p.m..';
            }
        }
    }

    return $payload;
}

/**
 * Canon (§6): «команда пересобрать тексты существующих карточек по этому правилу (без покупок)». A day dealt before the
 * rule kept the model's doubled dot on every card that shows the line; the command rebuilds exactly those strings from
 * the frame and its filler. CATCHES a card left with «p.m..», an unrelated «..» taken for one, a write without `--apply`,
 * and a second run that finds something again.
 */
it('rebuilds the doubled dot of dealt cards from the frame and its filler, once, and only with --apply', function () {
    app()->instance(PlanModelPort::class, new FakePlanModel(lesson: rctLesson(...)));
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 2])['id'];
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();
    planOpenDay($this, $token, $id, 1);

    // The day as it was dealt before the rule: every copy of the line with the dot doubled, and one «..» of somebody else's.
    $dayId = DB::table('plan_days')->where('plan_id', $id)->where('number', 1)->value('id');
    $touched = 0;
    foreach (DB::table('day_cards')->where('day_id', $dayId)->get(['id', 'payload']) as $card) {
        $json = (string) $card->payload;
        $was = str_replace('"Okay, he will rest until 3 p.m."', '"Okay, he will rest until 3 p.m.."', $json);
        if ($was !== $json) {
            DB::table('day_cards')->where('id', $card->id)->update(['payload' => $was]);
            $touched++;
        }
    }
    $other = DB::table('day_cards')->where('day_id', $dayId)->where('kind', 'speak_answer')->orderBy('position')->first();
    $payload = json_decode((string) $other->payload, true);
    $payload['own_line']['text_native'] = 'Подождите..';
    DB::table('day_cards')->where('id', $other->id)->update(['payload' => json_encode($payload, JSON_UNESCAPED_UNICODE)]);
    $doubled = static fn (): int => DB::table('day_cards')->where('day_id', $dayId)->where('payload', 'like', '%until 3 p.m..%')->count();

    expect($touched)->toBeGreaterThan(1)->and($doubled())->toBe($touched);

    expect(Artisan::call('plan:rebuild-card-texts', ['--plan' => [$id]]))->toBe(0)
        ->and(Artisan::output())->toContain('[dry-run] план '.$id.' · день 1 ·')->toContain("к исправлению: карточек {$touched}")
        ->and($doubled())->toBe($touched);

    Artisan::call('plan:rebuild-card-texts', ['--plan' => [$id], '--apply' => true]);
    expect(Artisan::output())->toContain("исправлено: карточек {$touched}")
        ->and($doubled())->toBe(0)
        ->and(DB::table('day_cards')->where('day_id', $dayId)->where('payload', 'like', '%Okay, he will rest until 3 p.m.%')->count())->toBe($touched)
        // Not a sentence of a frame: left as it is.
        ->and(json_decode((string) DB::table('day_cards')->where('id', $other->id)->value('payload'), true)['own_line']['text_native'])->toBe('Подождите..');

    Artisan::call('plan:rebuild-card-texts', ['--plan' => [$id], '--apply' => true]);
    expect(Artisan::output())->toContain('исправлено: карточек 0, строк 0');
});
