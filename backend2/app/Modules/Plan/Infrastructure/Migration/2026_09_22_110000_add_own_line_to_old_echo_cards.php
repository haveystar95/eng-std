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
    /**
     * THE ECHO CARDS DEALT BEFORE CONV-2 GET THEIR OWN LINE (наряд BACK-TAILS-2, дополнение по отчёту клиента 1c).
     *
     * Until CONV-2 «Повтори через паузу» (`speak_echo`, кадр 35-3) stood on the PARTNER's line and carried it under
     * `partner_line`, with no `own_line` at all. Build 1.0.0 (19) draws the echo from `own_line` only and does not draw a
     * card without it — ten such cards on the live base, on closed days and on deleted plans. Each is given the learner's
     * line of ITS exchange (`payload.scene_id`, `payload.exchange.step`), built by the very function a new deal builds it
     * with (`DayDealer::ownLine()` → `CardObjects::ownLine()` over the dealer's material of the scene).
     *
     * The key is ADDED and nothing else of the payload is touched (`jsonb_set`): the old `partner_line`, `expected_text`,
     * `coverage_min` stay as they were dealt. A card whose line cannot be found — no plan, no scene, no lesson, no such
     * exchange, no line of the learner's — is left as it is: nothing is invented for it.
     *
     * Reversible: back, `own_line` leaves the echo cards whose `partner_line` is not their own line — the old form, where
     * `partner_line` is the partner's `xN` and the added `own_line` the learner's `xNb`. The echo of CONV-2 (the same line
     * under both keys) and today's (no `partner_line` at all) never match.
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
            $line = $dealer->ownLine($plan, PlanSceneId::fromString($sceneId), $step);
            if ($line === null) {
                continue;
            }
            DB::update(
                "UPDATE day_cards SET payload = jsonb_set(payload, '{own_line}', CAST(? AS jsonb)) WHERE id = ?",
                [json_encode($line, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $card->id],
            );
        }
    }

    public function down(): void
    {
        DB::statement(
            "UPDATE day_cards SET payload = payload - 'own_line' WHERE kind = 'speak_echo'"
            ." AND payload->'own_line' IS NOT NULL AND payload->'partner_line' IS NOT NULL"
            ." AND payload->'partner_line'->>'ref' IS DISTINCT FROM payload->'own_line'->>'ref'",
        );
    }
};
