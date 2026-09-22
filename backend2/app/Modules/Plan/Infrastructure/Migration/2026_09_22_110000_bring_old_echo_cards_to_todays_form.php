<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Service\DayDealer;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** The share of the echo's words the deal before FIX-2 asked for — what every old echo card carries, and gets back. */
    private const COVERAGE_MIN = 0.7;

    /**
     * THE ECHO CARDS DEALT BEFORE CONV-2 TAKE TODAY'S FORM (наряд BACK-TAILS-2, дополнение по отчёту клиента 1c).
     *
     * Until CONV-2 «Повтори через паузу» (`speak_echo`, кадр 35-3) stood on the PARTNER's line: `partner_line` was that
     * line, `expected_text` its text, and there was no `own_line` at all. Build 1.0.0 (19) draws the echo from `own_line`
     * only and does not draw a card without it — ten such cards on the live base, on closed days and on deleted plans.
     * Each is brought to the form a new deal gives ITS exchange (`payload.scene_id`, `payload.exchange.step`), built by the
     * very function the deal builds it with (`DayDealer::echoOf()` → `SpeakCards::echoLine()` over the dealer's material):
     * `own_line` — the learner's line, `expected_text` — its text, `speech_mode` — the echo's mode; `coverage_min`, the
     * rule before FIX-2, goes. `partner_line` stays as it was dealt — nobody reads it any more, and it is the way back.
     * Nothing else of the payload is touched. A card whose line cannot be found — no plan, no scene, no lesson, no such
     * exchange, no line of the learner's — is left as it is: nothing is invented for it.
     *
     * Reversible, card for card: back, the echo cards whose `partner_line` is not their own line (the old form: the
     * partner's `xN` beside the learner's `xNb`; CONV-2's echo carries the same line under both keys, today's no
     * `partner_line` at all) lose `own_line`, and `expected_text` is the partner's text again. The rule of speech goes back
     * to what the deal that dealt the DAY wrote: a day dealt before FIX-2 (its other cards carry `coverage_min`) gets the
     * deal's constant {@see COVERAGE_MIN} back and loses `speech_mode`; a day dealt after FIX-2 — the owner's gym day 1,
     * dealt between FIX-2 and CONV-2 — had `speech_mode` all along and keeps it.
     */
    public function up(): void
    {
        $cards = DB::table('day_cards as c')
            ->join('plan_days as d', 'd.id', '=', 'c.day_id')
            ->where('c.kind', 'speak_echo')
            ->whereRaw("c.payload->'own_line' IS NULL")
            ->orderBy('c.id')
            ->get(['c.id', 'c.payload', 'd.plan_id']);
        if ($cards->isEmpty()) {
            return;
        }

        $plans = app(PlanRepository::class);
        $dealer = app(DayDealer::class);
        /** @var array<string, Plan|null> $loaded */
        $loaded = [];
        foreach ($cards as $card) {
            $payload = json_decode((string) $card->payload, true);
            $sceneId = is_array($payload) ? ($payload['scene_id'] ?? null) : null;
            $step = is_array($payload) && is_array($payload['exchange'] ?? null) ? ($payload['exchange']['step'] ?? null) : null;
            $planId = (string) $card->plan_id;
            if (! array_key_exists($planId, $loaded)) {
                $loaded[$planId] = $plans->findById(PlanId::fromString($planId));
            }
            $plan = $loaded[$planId];
            if ($plan === null || ! is_string($sceneId) || ! is_int($step)) {
                continue;
            }
            $fresh = $dealer->echoOf($plan, PlanSceneId::fromString($sceneId), $step);
            if ($fresh === null) {
                continue;
            }
            $today = array_intersect_key($fresh, array_flip(['own_line', 'expected_text', 'speech_mode']));
            DB::update(
                "UPDATE day_cards SET payload = (payload - 'coverage_min') || CAST(? AS jsonb) WHERE id = ?",
                [json_encode($today, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $card->id],
            );
        }
    }

    public function down(): void
    {
        DB::update(
            'UPDATE day_cards AS c SET payload = CASE'
            .' WHEN EXISTS (SELECT 1 FROM day_cards AS s WHERE s.day_id = c.day_id AND s.id <> c.id'
            ." AND s.payload->'coverage_min' IS NOT NULL)"
            ." THEN (c.payload - 'own_line' - 'speech_mode')"
            ." || jsonb_build_object('expected_text', c.payload->'partner_line'->'text_target', 'coverage_min', CAST(? AS numeric))"
            ." ELSE (c.payload - 'own_line') || jsonb_build_object('expected_text', c.payload->'partner_line'->'text_target')"
            .' END'
            ." WHERE c.kind = 'speak_echo' AND c.payload->'own_line' IS NOT NULL AND c.payload->'partner_line' IS NOT NULL"
            ." AND c.payload->'partner_line'->>'ref' IS DISTINCT FROM c.payload->'own_line'->>'ref'",
            [self::COVERAGE_MIN],
        );
    }
};
