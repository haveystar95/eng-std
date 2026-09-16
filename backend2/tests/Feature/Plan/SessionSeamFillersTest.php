<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Domain\Assembly\PhraseSeries;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

/**
 * THE SEAM JUDGE'S «NO», FROM THE LESSON TO THE DAY (наряд SESSION-1e, разд. 4): what the judge said while the lesson was
 * written lies in `checks_json` of the scene as `filler.native_seam` at `pN.fK` — and the day dealt from that scene reads it
 * there, with no call of its own: the filler is on no card, unless the dialogue says it.
 */

// Canon (SESSION-1e, разд. 4): «новых вызовов нет — находки читаются из сцены». Catches a day that deals without the scene's
// findings (the unit rule never reached), a judge called again when the day is opened, and a said filler hidden.
it('deals a day without the fillers its lesson\'s seam judge said do not read — read from the scene, no call of its own', function () {
    $fake = new FakePlanModel(judge: static fn ($request): array => ['verdicts' => array_map(
        static fn (string $id): array => ['id' => $id, 'reads' => ! in_array($id, ['p1.f1', 'p1.f2', 'p3.f3'], true)],
        $request->ids(),
    )]);
    app()->instance(PlanModelPort::class, $fake);
    [, $token] = planLearner();

    $id = planCreate($this, $token, ['days_total' => 1, 'level' => 'intermediate'])['id'];
    $findings = json_decode((string) DB::table('plan_scenes')->where('plan_id', $id)->orderBy('order')->value('checks_json'), true);
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();
    $judged = $fake->judgeCalls;
    $cards = planOpenDay($this, $token, $id, 1)['cards'];

    $shown = [];
    foreach ($cards as $card) {
        $frames = [];
        $collect = static function (mixed $value) use (&$collect, &$frames): void {
            if (! is_array($value)) {
                return;
            }
            if (isset($value['ref'], $value['slot']['fillers']) && is_array($value['slot']['fillers'])) {
                $frames[] = [$value['ref'], array_column($value['slot']['fillers'], 'index')];
            }
            foreach ($value as $item) {
                $collect($item);
            }
        };
        $collect($card['payload']);
        if ($card['unit']['kind'] === 'phrase' && isset($card['payload']['chips'])) {
            $frames[] = [$card['payload']['correct_frame'] ?? $card['unit']['ref'], array_column($card['payload']['chips'], 'index')];
        }
        foreach ($frames as [$ref, $indexes]) {
            foreach ($indexes as $index) {
                $shown[$ref][$index] = true;
            }
        }
        if ($card['unit']['kind'] === 'phrase') {
            $filler = PhraseSeries::fillerOf(CardKind::from($card['kind']), $card['payload']);
            expect([$card['unit']['ref'], $filler])->not->toBe(['p1', 1])
                ->and([$card['unit']['ref'], $filler])->not->toBe(['p3', 2]);
        }
    }

    expect(array_values(array_filter(array_map(
        static fn (array $f): ?string => $f['code'] === 'filler.native_seam' ? $f['address'] : null,
        $findings,
    ))))->toEqualCanonicalizing(['p1.f1', 'p1.f2', 'p3.f3'])
        ->and($fake->judgeCalls)->toBe($judged)
        // «neck» and «constant» are nowhere; «lower back» — the said one — is where it was.
        ->and(array_keys($shown['p1'] ?? []))->toEqualCanonicalizing([0, 2])
        ->and(array_keys($shown['p3'] ?? []))->toEqualCanonicalizing([0, 1])
        ->and(array_keys($shown['p2'] ?? []))->toEqualCanonicalizing([0, 1, 2]);
});
