<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Assembly\PhraseSeries;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * A FRAME THROUGH DIFFERENT WINDOWS, OVER HTTP (наряд SESSION-1d, разд. 4; DECISIONS п. 327): a phrase card failed once is
 * dealt again at the end of its stage as the SAME kind said with ANOTHER filler; failed again, its frame comes back on the
 * next day as the kind it failed as, said with another filler still — once. A phrase said aloud and given up on after two
 * attempts with a microphone is such a failure; a skip before the second attempt, or for want of a microphone, is not.
 *
 * Real days of the fake doctor lesson, dealt by the server: the scene ids are generated, so which kinds a frame walks is
 * read off the day, never assumed.
 */

beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

/**
 * The filler a card on the wire is said with.
 *
 * @param  array<string, mixed>  $card
 */
function s1dFiller(array $card): ?int
{
    return PhraseSeries::fillerOf(CardKind::from($card['kind']), $card['payload']);
}

/**
 * The first card of today's own material of one of the kinds.
 *
 * @param  list<array<string, mixed>>  $cards
 * @param  list<CardKind>  $kinds
 * @param  list<string>  $skip  frames already used by the test
 * @param  bool  $withFiller  only a card said with a filler of its own — one there is another window to vary
 * @return array<string, mixed>
 */
function s1dPick(array $cards, array $kinds, array $skip = [], bool $withFiller = true): array
{
    foreach ($cards as $card) {
        if ($card['source'] === 'today' && in_array(CardKind::from($card['kind']), $kinds, true)
            && ! in_array($card['unit']['ref'], $skip, true) && (! $withFiller || s1dFiller($card) !== null)) {
            return $card;
        }
    }
    throw new RuntimeException('No such card dealt.');
}

/**
 * The fillers the cards of one frame are said with on a day, and the fillers the frame has.
 *
 * @param  list<array<string, mixed>>  $cards
 * @return array{used: list<int>, all: list<int>}
 */
function s1dFrameFillers(array $cards, string $ref): array
{
    $used = [];
    $all = [];
    foreach ($cards as $card) {
        if ($card['unit']['kind'] !== 'phrase' || $card['unit']['ref'] !== $ref) {
            continue;
        }
        $filler = s1dFiller($card);
        if ($filler !== null && ! in_array($filler, $used, true)) {
            $used[] = $filler;
        }
        foreach ($card['payload']['frame']['slot']['fillers'] ?? [] as $each) {
            $all[$each['index']] = $each['index'];
        }
    }

    return ['used' => $used, 'all' => array_values($all)];
}

/**
 * @param  list<array<string, mixed>>  $cards
 * @return list<array<string, mixed>> the cards a day dealt back
 */
function s1dReturned(array $cards): array
{
    return array_values(array_filter($cards, static fn (array $c): bool => $c['source'] === 'returned' && $c['retry_of'] === null));
}

// Canon (SESSION-1d, разд. 4): «провалил карточку фразы → копия в конец этапа ТОГО ЖЕ вида с другим наполнением; в день
// возврата — видом последнего провала, снова с другим наполнением; единица возвращается один раз». Catches a copy that is
// the same card reshuffled, a copy of another kind, a copy said with a taken filler while a free one is there, a return
// that is phrase_slot whatever failed, a return said with the failed filler, and a return that sends its frame back again.
it('deals a failed phrase recognition again as the same kind with another filler, and brings its frame back tomorrow as that kind with another filler still', function () {
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 3, 'level' => 'intermediate'])['id'];
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();

    $day1 = planOpenDay($this, $token, $id, 1)['cards'];
    $card = s1dPick($day1, PhraseSeries::CYCLE);
    $ref = $card['unit']['ref'];
    $frame = s1dFrameFillers($day1, $ref);
    $free = array_values(array_diff($frame['all'], $frame['used']));

    $first = planAnswer($this, $token, $id, 1, $card['id'], 'failed');
    $copy = $first['requeued'];
    expect($copy)->not->toBeNull()
        ->and($copy['kind'])->toBe($card['kind'])
        ->and($copy['unit'])->toBe($card['unit'])
        ->and($copy['retry_of'])->toBe($card['id'])
        ->and(s1dFiller($copy))->not->toBe(s1dFiller($card))
        ->and(in_array(s1dFiller($copy), $free === [] ? $frame['all'] : $free, true))->toBeTrue();

    $second = planAnswer($this, $token, $id, 1, $copy['id'], 'failed', 2);
    expect($second['unit'])->toMatchArray(['kind' => 'phrase', 'ref' => $ref, 'returns_tomorrow' => true, 'returns_day' => 2]);
    planWalkDay($this, $token, $id, 1);
    planShiftDay($id);

    $back = array_values(array_filter(s1dReturned(planOpenDay($this, $token, $id, 2)['cards']), static fn (array $c): bool => $c['unit']['ref'] === $ref));
    expect($back)->toHaveCount(1)
        ->and($back[0]['kind'])->toBe($card['kind'])
        ->and($back[0]['payload']['scene_id'])->toBe($card['payload']['scene_id'])
        ->and($back[0]['source_day'])->toBe(1)
        ->and(s1dFiller($back[0]))->not->toBe(s1dFiller($copy));

    // A return failed twice sends its frame nowhere again.
    $again = planAnswer($this, $token, $id, 2, $back[0]['id'], 'failed');
    expect($again['requeued']['kind'])->toBe($card['kind'])
        ->and(planAnswer($this, $token, $id, 2, $again['requeued']['id'], 'failed', 2)['unit'])->toMatchArray(['returns_tomorrow' => false, 'returns_day' => null]);
});

// Canon (SESSION-1d, DECISIONS п. 327; наряд FIX-2 п. 5): «провал произнесения = skipped с attempts ≥ 2 и без no_mic:
// возвращается произнесением; «Пропустить» до второй попытки и отказ микрофона — без последствий». Catches a skip taken
// for a lapse whatever its attempts, a dead microphone counted against the learner, and a frame said aloud coming back
// as a recognition. «Скажи целиком» already walks every value of its window, so its copy is the whole card again.
it('deals «Скажи целиком» given up on after two attempts again and returns it tomorrow as said aloud, a skip before that or without a microphone dealing nothing', function () {
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 3, 'level' => 'beginner'])['id'];
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();

    $day1 = planOpenDay($this, $token, $id, 1)['cards'];
    $whole = s1dPick($day1, [CardKind::PhraseOtherSlot], [], withFiller: false);
    $early = s1dPick($day1, [CardKind::PhraseOtherSlot], [$whole['unit']['ref']], withFiller: false);
    $noMic = s1dPick($day1, [CardKind::PhraseOtherSlot], [$whole['unit']['ref'], $early['unit']['ref']], withFiller: false);

    $first = planAnswer($this, $token, $id, 1, $whole['id'], 'skipped', 2, ['heard' => 'it']);
    $copy = $first['requeued'];
    expect($copy)->not->toBeNull()
        ->and($copy['kind'])->toBe('phrase_other_slot')
        ->and(array_column($copy['payload']['rounds'], 'filler_index'))->toBe(array_column($whole['payload']['rounds'], 'filler_index'))
        ->and($copy['payload']['own_round']['judge'])->toBeTrue()
        ->and($first['unit']['returns_tomorrow'])->toBeFalse();

    expect(planAnswer($this, $token, $id, 1, $early['id'], 'skipped', 1))->toMatchArray(['requeued' => null])
        ->and(planAnswer($this, $token, $id, 1, $noMic['id'], 'skipped', 2, ['no_mic' => true]))->toMatchArray(['requeued' => null])
        ->and(planAnswer($this, $token, $id, 1, $copy['id'], 'skipped', 2)['unit'])->toMatchArray(['returns_tomorrow' => true, 'returns_day' => 2]);
    planWalkDay($this, $token, $id, 1);
    planShiftDay($id);

    $back = s1dReturned(planOpenDay($this, $token, $id, 2)['cards']);
    expect(array_map(static fn (array $c): string => $c['kind'].'@'.$c['unit']['ref'], $back))->toBe(['phrase_other_slot@'.$whole['unit']['ref']]);
});

// Canon (SESSION-1d, разд. 4): «единица возвращается ровно один раз — видом, которым её провалили ПОСЛЕДНИЙ раз». Found by
// the live run: a frame failed first as a recognition and then as said aloud came back as the recognition — the first
// returning card of its day, not the last. Catches a return taken by the day's order instead of the latest failure, and a
// frame dealt back twice.
it('brings a frame failed as a recognition and then as said aloud back once, as said aloud — its last failure', function () {
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 3, 'level' => 'intermediate'])['id'];
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();

    $day1 = planOpenDay($this, $token, $id, 1)['cards'];
    $said = s1dPick($day1, [CardKind::PhraseOtherSlot], [], withFiller: false);
    $ref = $said['unit']['ref'];
    $recognition = s1dPick(array_values(array_filter($day1, static fn (array $c): bool => $c['unit']['ref'] === $ref)), PhraseSeries::CYCLE);

    $copy = planAnswer($this, $token, $id, 1, $recognition['id'], 'failed')['requeued'];
    expect(planAnswer($this, $token, $id, 1, $copy['id'], 'failed', 2)['unit']['returns_tomorrow'])->toBeTrue();
    $saidCopy = planAnswer($this, $token, $id, 1, $said['id'], 'skipped', 2)['requeued'];
    expect($saidCopy['kind'])->toBe('phrase_other_slot')
        ->and(planAnswer($this, $token, $id, 1, $saidCopy['id'], 'skipped', 2)['unit']['returns_tomorrow'])->toBeTrue();
    planWalkDay($this, $token, $id, 1);
    planShiftDay($id);

    $back = array_values(array_filter(s1dReturned(planOpenDay($this, $token, $id, 2)['cards']), static fn (array $c): bool => $c['unit']['ref'] === $ref));
    expect(array_column($back, 'kind'))->toBe(['phrase_other_slot'])
        ->and(array_column($back[0]['payload']['rounds'], 'filler_index'))->toBe(array_column($said['payload']['rounds'], 'filler_index'));
});
