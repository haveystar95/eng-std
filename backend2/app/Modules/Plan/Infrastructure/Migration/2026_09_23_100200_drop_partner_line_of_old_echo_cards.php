<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Assembly\CardObjects;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Exception\SceneNotFound;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The moment echo cards stopped being dealt with `partner_line` on the live server — наряд BACK-TAILS-2 went out at
     * 11:13–11:17 UTC, 22.09. A day opened before it had its echo dealt with the partner's line beside the learner's.
     */
    private const DEALT_WITHOUT_PARTNER_LINE_FROM = '2026-09-22 11:13:00+00';

    /**
     * THE LAST `partner_line` OF AN ECHO CARD GOES (наряд FIX-3 §11: «partner_line у эха снят везде, остатки удалить»).
     *
     * The echo («Повтори через паузу», `speak_echo`, кадр 35-3) is the learner's own line since CONV-2, and since
     * BACK-TAILS-2 it is dealt without the partner's (DECISIONS п. 388); the cards dealt before kept the partner's line
     * under `partner_line` as the way back of that migration, and nobody has read it since build 1.0.0 (19) — ten on the
     * live base, one on the e2e stand. The key is taken off them; nothing else of the payload is touched.
     *
     * Reversible: back, an echo card of a day opened before {@see DEALT_WITHOUT_PARTNER_LINE_FROM} that has no
     * `partner_line` gets the partner's line of its own exchange again (`payload.scene_id`, `payload.exchange.step`), built
     * the way a card's partner line is built ({@see CardObjects::partnerLine()}) — which is what every one of the eleven
     * carried (the partner's `xN` beside the learner's `xNb`). A day opened after that moment never had one and gets none.
     */
    public function up(): void
    {
        DB::update("UPDATE day_cards SET payload = payload - 'partner_line' WHERE kind = 'speak_echo' AND payload->'partner_line' IS NOT NULL");
    }

    public function down(): void
    {
        $cards = DB::table('day_cards as c')
            ->join('plan_days as d', 'd.id', '=', 'c.day_id')
            ->where('c.kind', 'speak_echo')
            ->whereRaw("c.payload->'partner_line' IS NULL")
            ->where('d.opened_at', '<', self::DEALT_WITHOUT_PARTNER_LINE_FROM)
            ->orderBy('c.id')
            ->get(['c.id', 'c.payload', 'd.plan_id']);

        $plans = app(PlanRepository::class);
        /** @var array<string, Plan|null> $loaded */
        $loaded = [];
        foreach ($cards as $card) {
            $payload = json_decode((string) $card->payload, true);
            $sceneId = is_array($payload) ? ($payload['scene_id'] ?? null) : null;
            $step = is_array($payload) && is_array($payload['exchange'] ?? null) ? ($payload['exchange']['step'] ?? null) : null;
            $planId = (string) $card->plan_id;
            $loaded[$planId] ??= $plans->findById(PlanId::fromString($planId));
            $plan = $loaded[$planId];
            if ($plan === null || ! is_string($sceneId) || ! is_int($step)) {
                continue;
            }
            try {
                $exchange = $plan->scene(PlanSceneId::fromString($sceneId))->lesson()?->exchange($step);
            } catch (SceneNotFound) {
                continue;
            }
            $line = CardObjects::partnerLine($exchange);
            if ($line === null) {
                continue;
            }
            DB::update(
                "UPDATE day_cards SET payload = payload || jsonb_build_object('partner_line', CAST(? AS jsonb)) WHERE id = ?",
                [json_encode($line, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $card->id],
            );
        }
    }
};
