<?php

declare(strict_types=1);

/**
 * BACK-TAILS-2, дополнение · WHAT THE MIGRATION `2026_09_22_110000_bring_old_echo_cards_to_todays_form` WILL DO — read only.
 *
 *   docker exec -e DB_DATABASE=<db> wt_tails2 php docs/research/back-tails-2/tools/echo-own-line.php
 *
 * Every `speak_echo` without `own_line`: its plan and day, the exchange it stands on, the partner's line it carries, the
 * three keys the migration will write — from the same function a new deal uses (`DayDealer::echoOf()`) — whether the card
 * then equals the fresh deal of its exchange but for `partner_line`, and what the way back gives it; or why it gets none.
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
$brought = 0;
$fresh = 0;
foreach ($cards as $card) {
    $payload = json_decode((string) $card->payload, true);
    $step = $payload['exchange']['step'] ?? null;
    $plan = app(PlanRepository::class)->findById(PlanId::fromString((string) $card->plan_id));
    $echo = $plan === null || ! is_int($step) || ! is_string($payload['scene_id'] ?? null)
        ? null
        : app(DayDealer::class)->echoOf($plan, PlanSceneId::fromString($payload['scene_id']), $step);
    // The day's deal, for the way back: before FIX-2 its other cards carry `coverage_min`.
    $beforeFix2 = DB::table('day_cards')->where('day_id', DB::table('day_cards')->where('id', $card->id)->value('day_id'))
        ->where('id', '<>', $card->id)->whereRaw("payload->'coverage_min' IS NOT NULL")->exists();
    printf("  %s · план %s (%s) · день %d (%s) · итог %s · обмен %s · partner_line %s «%s» · правило речи %s\n",
        $card->id, $card->plan_id, $card->plan_status, $card->number, $card->day_status, $card->result ?? '—',
        (string) $step, (string) ($payload['partner_line']['ref'] ?? '—'), (string) ($payload['partner_line']['text_target'] ?? ''),
        array_key_exists('coverage_min', $payload) ? 'coverage_min '.json_encode($payload['coverage_min']) : 'speech_mode '.json_encode($payload['speech_mode'] ?? null));
    if ($echo === null) {
        echo "    → реплика НЕ НАЙДЕНА — карточка останется как есть\n";

        continue;
    }
    $brought++;
    $after = [...array_diff_key($payload, ['coverage_min' => true]), ...array_intersect_key($echo, array_flip(['own_line', 'expected_text', 'speech_mode']))];
    $same = array_diff_key($after, ['partner_line' => true]) == $echo;
    $fresh += $same ? 1 : 0;
    printf("    → own_line %s «%s» · expected_text «%s» · speech_mode %s · coverage_min снят: %s · равна свежей раздаче (кроме partner_line): %s\n",
        $echo['own_line']['ref'], $echo['own_line']['text_target'], $echo['expected_text'], $echo['speech_mode'],
        array_key_exists('coverage_min', $payload) ? 'да' : 'не было', $same ? 'да' : 'НЕТ');
    printf("    ← откат: expected_text «%s» · %s\n", (string) ($payload['partner_line']['text_target'] ?? ''),
        $beforeFix2 ? 'coverage_min 0.7 обратно, speech_mode снять (день роздан до FIX-2)' : 'speech_mode остаётся, coverage_min не появляется (день роздан после FIX-2)');
}
printf("миграция приведёт к нынешней форме: %d из %d · равны свежей раздаче своего обмена (кроме partner_line): %d\n", $brought, $cards->count(), $fresh);
