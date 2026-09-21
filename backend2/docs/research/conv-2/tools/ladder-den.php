<?php

declare(strict_types=1);

/**
 * CONV-2 · THE OWNER'S GYM DAY 1, DEALT AGAIN IN MEMORY (report §1.5–1.6, §2.5) — «Фразы» (the rounds of every «Скажи целиком»,
 * the stage's seconds against its ceiling) and «Говорю сам» (whose line each card says).
 *
 * The day is dealt by the dealer itself (`DayDealer::outline()` — the same assembler, nothing written, no id kept) over
 * the plan as the database holds it, so the SAME harness run on the code before the наряд and on the code after it
 * answers «лестница или фильтр швов» and «чья реплика у эха» on the owner's own day. The session is READ ONLY: this reads
 * the live database and must not be able to write to it.
 *
 *   before — the live stack (main tree):  docker cp …/ladder-den.php wt_app:/tmp/ && docker exec -e APP_ROOT=/app wt_app php /tmp/ladder-den.php <plan> <day>
 *   after  — the наряд's tree:            docker compose run --rm --no-deps -v <worktree>/backend2:/wt -w /wt -e APP_ROOT=/wt app php docs/research/conv-2/tools/ladder-den.php <plan> <day>
 */

use App\Modules\Plan\Application\Service\DayDealer;
use App\Modules\Plan\Domain\Assembly\PhrasesStage;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\Stage;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$root = getenv('APP_ROOT') ?: dirname(__DIR__, 4);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Nothing below may write — and the database is told so, not asked nicely.
DB::statement('SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY');

[$planId, $number] = [$argv[1] ?? '01M32DX8QCABM348XP45Z1ZD4M', (int) ($argv[2] ?? 1)];
$plan = app(PlanRepository::class)->findById(PlanId::fromString($planId));
if ($plan === null) {
    fwrite(STDERR, "нет плана {$planId}\n");
    exit(1);
}
$day = $plan->day($number);
$cards = app(DayDealer::class)->outline($plan, $day);

printf("план %s · день %d · уровень %s · база %s · потолок «Фраз» %d с\n", $planId, $number, $plan->level()->value, (string) config('database.connections.pgsql.database'), (int) config('plan.phrases_budget', PhrasesStage::BUDGET));

$phrases = array_values(array_filter($cards, static fn (DayCard $c): bool => $c->stage() === Stage::Phrases));
$seconds = (new PhrasesStage)->seconds(array_map(
    static fn (DayCard $c): \App\Modules\Plan\Domain\Assembly\CardDraft => new \App\Modules\Plan\Domain\Assembly\CardDraft($c->kind(), $c->unitKind(), $c->unitRef(), $c->payload()),
    $phrases,
));
printf("«Фразы»: %d карточек · %d с по DayPace\n", count($phrases), $seconds);
foreach ($phrases as $card) {
    if ($card->kind() !== CardKind::PhraseOtherSlot) {
        continue;
    }
    $payload = $card->payload();
    $frame = (string) ($payload['frame']['frame_target'] ?? '');
    $fillers = array_map(static fn (array $f): string => (string) $f['target'], $payload['frame']['slot']['fillers'] ?? []);
    $rounds = array_map(static fn (array $r): string => (string) $r['expected_text'], $payload['rounds'] ?? []);
    printf("  %-3s %-32s значений %d (%s) · кругов %d%s\n", $card->unitRef(), $frame, count($fillers), implode(' / ', $fillers), count($rounds),
        ($payload['own_round'] ?? null) === null ? '' : ' + своё');
}

$pace = app(\App\Modules\Plan\Domain\Service\DayPace::class);
printf("день: %d карточек · %d с = %d мин по DayPace (потолок карточек — %d мин)\n", count($cards), $pace->secondsOf($cards), (int) ceil($pace->secondsOf($cards) / 60), (int) config('plan.day_cards_budget', 32));

echo "«Говорю сам»:\n";
foreach ($cards as $card) {
    if ($card->stage() !== Stage::Speak) {
        continue;
    }
    $payload = $card->payload();
    $line = $payload['own_line'] ?? $payload['partner_line'] ?? null;
    $whose = isset($payload['own_line']) ? 'ученик' : (isset($payload['partner_line']) && $card->kind() === CardKind::SpeakEcho ? 'СОБЕСЕДНИК' : 'ученик');
    printf("  %-13s %-4s %-10s %s\n", $card->kind()->value, $card->unitRef(), $whose, is_array($line) ? (string) $line['text_target'] : '');
}
