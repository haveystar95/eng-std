<?php

declare(strict_types=1);

/**
 * CONV-2 · THE OWNER'S JUDGED ATTEMPTS OF 21.09, JUDGED AGAIN (report §1.7–1.8, §2.4).
 *
 * The cards are the ones his gym day 1 dealt (`den/gym-day1-cards.json`, payloads as stored), the attempts are what his
 * phone sent (`api_request_logs`, inbound `…/judge`), and each goes through the server's own judge — `SlotJudge`, the
 * code first and `slot_judge.v3` for what the code cannot say — against a plan of the same pair and level on the e2e
 * stand. Nothing is written to any card: the card is built in memory from its payload. Prints было (the verdict his
 * phone got) / стало, and the money.
 *
 *   docker exec -e PLAN_SLOT_JUDGE_QUOTA_STORE=array wt_conv2_e2e php docs/research/conv-2/tools/judge-den.php
 */

use App\Modules\Plan\Application\Service\SlotJudge;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\CardSource;
use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\UnitKind;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = (string) config('database.connections.pgsql.database');
if ($database !== 'wordtrainer_e2e_test') {
    fwrite(STDERR, "ОТКАЗ: харнесс покупает вызовы — только на wordtrainer_e2e_test, а не на «{$database}».\n");
    exit(1);
}

$base = dirname(__DIR__);
$cards = [];
foreach (json_decode((string) file_get_contents("{$base}/den/gym-day1-cards.json"), true, flags: JSON_THROW_ON_ERROR) as $row) {
    $cards[$row['id']] = $row;
}
// An intermediate plan, English for a Russian speaker, like the owner's — the judge reads its languages and level.
$plan = app(PlanRepository::class)->findById(PlanId::fromString($argv[1] ?? '01M2QRA8K1W02SZNZ35ZG5DXPD'));
if ($plan === null) {
    fwrite(STDERR, "нет плана\n");
    exit(1);
}

/** The attempts his phone sent, in order, with the verdict it got (inbound log of 21.09) — the cards of the report. */
$attempts = [
    ['01M32E0RJGG3PSGX3RAXFKAKVC', 'Yes it my first visit', 'code: «Каркас не прозвучал — скажи его целиком»'],
    ['01M32E0RJGG3PSGX3RAXFKAKVC', 'Yes it is my first visit', 'code: «Каркас не прозвучал — скажи его целиком»'],
    ['01M32E0RJGG3PSGX3RAXFKAKVC', 'Yes it is my first day', 'code: «Каркас не прозвучал — скажи его целиком»'],
    ['01M32E0RJG4SVN03HW5V38V2SY', 'That works for me', 'model: «Ты не сказал слово в пропуске.»'],
    ['01M32E0RJG4SVN03HW5V38V2SY', 'That works for me for weekdays', 'code: зачёт (weekdays)'],
    ['01M32E0RJGJVRCAX6HS2QFTF3M', 'I will return the towel', 'model: «Ты не назвал то, что нужно вернуть после тренировки.»'],
    ['01M32E0RJGJVRCAX6HS2QFTF3M', 'I will return the best key', 'model: зачёт (the best key)'],
    ['01M32E0RJGGH5CZDK6HD6VH4D6', 'OK I will return', 'model: «Ты не сказал, что именно вернёшь.»'],
];

$judge = app(SlotJudge::class);
$now = new DateTimeImmutable;
$spent = 0.0;
$out = [];
foreach ($attempts as $i => [$cardId, $heard, $before]) {
    $row = $cards[$cardId];
    $card = DayCard::dealt(
        DayCardId::fromString($cardId), PlanDayId::fromString('01M32DXJ0000000000000000D1'), CardKind::from($row['kind'])->stage(),
        (int) $row['position'], CardKind::from($row['kind']), $row['payload'], CardSource::Today, null,
        UnitKind::from($row['kind'] === 'phrase_other_slot' ? 'phrase' : 'exchange'), (string) $row['unit_ref'],
    );
    $verdict = $judge->judge($plan, $card, $heard, $now);
    $spent += (float) ($verdict->costUsd ?? 0);
    $after = sprintf('%s: %s%s', $verdict->by, $verdict->accepted ? 'зачёт' : 'отказ', $verdict->reasonNative === null ? '' : " «{$verdict->reasonNative}»")
        .($verdict->slotValue === null ? '' : " (окно: {$verdict->slotValue})");
    $out[] = ['card' => $cardId, 'kind' => $row['kind'], 'unit' => $row['unit_ref'], 'heard' => $heard, 'before' => $before, 'after' => $after, 'latency_ms' => $verdict->latencyMs];
    printf("%d. %s %s — «%s»\n   было:  %s\n   стало: %s\n", $i + 1, $row['kind'], $row['unit_ref'], $heard, $before, $after);
}
printf("\nсудья: $%.6f\n", $spent);
file_put_contents("{$base}/live/judge-den.json", json_encode(['attempts' => $out, 'usd' => round($spent, 6)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");
