<?php

declare(strict_types=1);

/**
 * BACK-TAILS-2, дополнение · WHAT THE MIGRATION `2026_09_22_110000_add_own_line_to_old_echo_cards` WILL DO — read only.
 *
 *   docker exec -e DB_DATABASE=<db> wt_tails2 php docs/research/back-tails-2/tools/echo-own-line.php
 *
 * Every `speak_echo` without `own_line`: its plan and day, the exchange it stands on, the partner's line it carries, and
 * the learner's line the migration will add — from the same function (`DayDealer::ownLine()`) — or why it will add none.
 * The session is READ ONLY: this reads the live database and must not be able to write to it.
 */

use App\Modules\Plan\Application\Service\DayDealer;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

DB::statement('SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY');

$cards = DB::table('day_cards as c')
    ->join('plan_days as d', 'd.id', '=', 'c.day_id')
    ->join('plans as p', 'p.id', '=', 'd.plan_id')
    ->where('c.kind', 'speak_echo')
    ->whereRaw("c.payload->'own_line' IS NULL")
    ->orderBy('d.plan_id')->orderBy('d.number')
    ->get(['c.id', 'c.payload', 'c.result', 'd.plan_id', 'd.number', 'd.status as day_status', 'p.status as plan_status']);

printf("база %s · speak_echo без own_line: %d\n", (string) config('database.connections.pgsql.database'), $cards->count());
$added = 0;
foreach ($cards as $card) {
    $payload = json_decode((string) $card->payload, true);
    $step = $payload['exchange']['step'] ?? null;
    $plan = app(PlanRepository::class)->findById(PlanId::fromString((string) $card->plan_id));
    $line = $plan === null || ! is_int($step) || ! is_string($payload['scene_id'] ?? null)
        ? null
        : app(DayDealer::class)->ownLine($plan, PlanSceneId::fromString($payload['scene_id']), $step);
    $added += $line === null ? 0 : 1;
    printf("  %s · план %s (%s) · день %d (%s) · итог %s · обмен %s · partner_line %s «%s»\n    → own_line %s\n",
        $card->id, $card->plan_id, $card->plan_status, $card->number, $card->day_status, $card->result ?? '—',
        (string) $step, (string) ($payload['partner_line']['ref'] ?? '—'), (string) ($payload['partner_line']['text_target'] ?? ''),
        $line === null ? 'НЕ НАЙДЕНА — карточка останется как есть' : "{$line['ref']} «{$line['text_target']}» / «{$line['text_native']}»");
}
printf("миграция добавит own_line: %d из %d\n", $added, $cards->count());
