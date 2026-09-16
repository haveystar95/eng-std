<?php

declare(strict_types=1);

/**
 * SESSION-1d · THE DOCTOR'S DAY, DEALT AGAIN — AND ROLLED BACK. Day 1 of the e2e doctor plan dealt anew through the handlers
 * production runs (`OpenDay`), printed as the order asks (stage → cards → minutes by `DayPace`; per frame its fillers, its
 * cards with the kind and the filler of each, the distances between them, «one filler — once»), then the failure scenario
 * over `AnswerCard`: one recognition failed twice, one production said aloud and given up on twice — the copies dealt
 * today (kind, filler) and, on a review day opened after it, what came back (kind, filler).
 *
 * EVERYTHING HAPPENS IN ONE TRANSACTION THAT IS ROLLED BACK: day 1 of this plan is being walked by the client session
 * (SESSION-1b, 16.09) and has one day only — its cards, its answers and its calendar are left exactly as they were, the
 * review day included. What stays is the report printed here and the room written next to it as JSON. No model call, no
 * purchase: the lesson is the scene's stored one (`lesson_day.v4.4`).
 *
 *   docker compose exec -T -e DB_DATABASE=wordtrainer_e2e_test -e QUEUE_CONNECTION=sync -e SPEECH_ENABLED=false \
 *     app php docs/research/session-1d/tools/live-day.php [plan id]
 */

use App\Modules\Plan\Application\Command\AnswerCard;
use App\Modules\Plan\Application\Command\AnswerCardHandler;
use App\Modules\Plan\Application\Command\OpenDay;
use App\Modules\Plan\Application\Command\OpenDayHandler;
use App\Modules\Plan\Application\Port\LearnerCalendar;
use App\Modules\Plan\Application\Query\GetDayRoom;
use App\Modules\Plan\Application\Query\GetDayRoomHandler;
use App\Modules\Plan\Domain\Assembly\PhraseSeries;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Repository\DayCardRepository;
use App\Modules\Plan\Domain\Service\DayPace;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\CardResult;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Presentation\Http\PlanJson;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
require __DIR__.'/phrase-table.php';

$database = (string) config('database.connections.pgsql.database');
if ($database !== 'wordtrainer_e2e_test') {
    fwrite(STDERR, "Runs only against wordtrainer_e2e_test (got {$database}).\n");
    exit(1);
}

$planId = $argv[1] ?? '01M2H13E1QT6F5D4FKJSEKTAD7';
$plan = DB::table('plans')->where('id', $planId)->first();
if ($plan === null) {
    fwrite(STDERR, "No plan {$planId} on {$database}.\n");
    exit(1);
}
$actor = UserId::fromString((string) $plan->user_id);
$scene = DB::table('plan_scenes')->where('plan_id', $planId)->orderBy('order')->first();
$pace = app(DayPace::class);
$cardsRepo = app(DayCardRepository::class);

/** @return array<string, mixed> what the rollback must give back */
$snapshot = static function () use ($planId): array {
    $days = DB::table('plan_days')->where('plan_id', $planId)->orderBy('number')->get(['number', 'status', 'cards_total', 'cards_done', 'minutes_spent', 'opens_on'])->map(static fn ($d): array => (array) $d)->all();
    $cards = DB::table('day_cards')->join('plan_days', 'plan_days.id', '=', 'day_cards.day_id')->where('plan_days.plan_id', $planId);

    return [
        'days' => $days,
        'cards' => (clone $cards)->count(),
        'answered' => (clone $cards)->whereNotNull('day_cards.answered_at')->count(),
        'hash' => md5((string) json_encode((clone $cards)->orderBy('day_cards.id')->get(['day_cards.id', 'day_cards.result', 'day_cards.attempts'])->all())),
    ];
};

/** @param list<DayCard> $cards */
$stageTable = static function (array $cards) use ($pace): void {
    echo "| этап | карточек | секунд по DayPace | минут |\n|---|---|---|---|\n";
    $total = 0;
    foreach (Stage::ordered() as $stage) {
        $of = array_values(array_filter($cards, static fn (DayCard $c): bool => $c->stage() === $stage));
        $seconds = $pace->secondsOf($of);
        $total += $seconds;
        echo '| '.$stage->value.' | '.count($of)." | {$seconds} | ".DayPace::minutes($seconds)." |\n";
    }
    echo '| **день** | **'.count($cards)."** | {$total} | **".DayPace::minutes($total)."** |\n";
};

$describe = static function (DayCard $card): string {
    $filler = PhraseSeries::fillerOf($card->kind(), $card->payload());
    $fillers = $card->payload()['frame']['slot']['fillers'] ?? [];
    $text = null;
    foreach ($fillers as $each) {
        if ($each['index'] === $filler) {
            $text = $each['target'];
        }
    }

    return $card->kind()->value.' '.$card->unitRef().' · наполнение '.($filler === null ? '—' : $filler.' «'.$text.'»');
};

$before = $snapshot();
echo "database={$database} plan={$planId} level={$plan->level} status={$plan->status} scene={$scene?->id} lesson={$scene?->prompt_version_lesson}\n";
echo 'до: день 1 — '.json_encode($before['days'][0] ?? null, JSON_UNESCAPED_UNICODE).", карточек {$before['cards']}, отвечено {$before['answered']}\n";

DB::beginTransaction();
try {
    $day1 = DB::table('plan_days')->where('plan_id', $planId)->where('number', 1)->first();
    DB::table('day_cards')->where('day_id', $day1->id)->delete();
    // The review day the returns come back on — this plan has one day only.
    DB::table('plan_days')->insert([
        'id' => Ulid::generate(), 'plan_id' => $planId, 'user_id' => $plan->user_id, 'number' => 2, 'type' => 'review',
        'scene_id' => null, 'status' => 'locked', 'opens_on' => null, 'cards_total' => 0, 'cards_done' => 0, 'minutes_spent' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    app(OpenDayHandler::class)(new OpenDay(PlanId::fromString($planId), 1, $actor));
    $cards = $cardsRepo->forDay(PlanDayId::fromString((string) $day1->id));

    echo "\n## День 1, пере-роздан\n\n";
    $stageTable($cards);
    s1dPrintPhrases(array_values(array_filter($cards, static fn (DayCard $c): bool => $c->stage() === Stage::Phrases)), $pace);

    $room = app(GetDayRoomHandler::class)(new GetDayRoom(PlanId::fromString($planId), 1, $actor));
    $roomJson = PlanJson::room($room);
    $out = __DIR__.'/../e2e-day-doctor.json';
    file_put_contents($out, json_encode($roomJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR)."\n");
    echo "\nroom → docs/research/session-1d/e2e-day-doctor.json (window.day.minutes_estimate=".json_encode($roomJson['window']['day']['minutes_estimate']).")\n";

    // ── the failure scenario ────────────────────────────────────────────────────────────────────────────────────────
    $answer = static fn (DayCard $card, CardResult $result, int $attempts, ?array $response = null) => app(AnswerCardHandler::class)(
        new AnswerCard(PlanId::fromString($planId), 1, $card->id(), $result, $attempts, $actor, $response),
    );
    $pick = static function (array $cards, array $kinds, array $skip = []): DayCard {
        foreach ($cards as $card) {
            if (in_array($card->kind(), $kinds, true) && ! in_array($card->unitRef(), $skip, true)
                && PhraseSeries::fillerOf($card->kind(), $card->payload()) !== null) {
                return $card;
            }
        }
        throw new RuntimeException('No such card.');
    };

    echo "\n## Сценарий провала\n\n";
    $recognition = $pick($cards, PhraseSeries::CYCLE);
    $first = $answer($recognition, CardResult::Failed, 1);
    $copy = $first->requeued;
    echo '- узнавание: '.$describe($recognition)." → failed\n";
    echo '  - копия сегодня: '.$describe($copy).' (позиция '.$copy->position().", retry_of = исходная)\n";
    $second = $answer($copy, CardResult::Failed, 2);
    echo '  - копия → failed: returns_tomorrow='.json_encode($second->unitReturns).', returns_day='.json_encode($second->returnsDay)."\n";

    $production = $pick($cards, [CardKind::PhraseOtherSlot, CardKind::PhraseRepeat], [$recognition->unitRef()]);
    $third = $answer($production, CardResult::Skipped, 2, ['heard' => '']);
    $copy2 = $third->requeued;
    echo '- произнесение: '.$describe($production)." → skipped, attempts 2\n";
    echo '  - копия сегодня: '.$describe($copy2).' (позиция '.$copy2->position().")\n";
    $fourth = $answer($copy2, CardResult::Skipped, 2);
    echo '  - копия → skipped, attempts 2: returns_tomorrow='.json_encode($fourth->unitReturns).', returns_day='.json_encode($fourth->returnsDay)."\n";

    $early = null;
    foreach ($cards as $card) {
        if (in_array($card->kind(), [CardKind::PhraseOtherSlot, CardKind::PhraseRepeat], true) && $card->unitRef() !== $production->unitRef() && $card->unitRef() !== $recognition->unitRef()) {
            $early = $card;
            break;
        }
    }
    if ($early !== null) {
        $fifth = $answer($early, CardResult::Skipped, 1);
        echo '- контроль: '.$describe($early).' → skipped с первой попытки: requeued='.json_encode($fifth->requeued !== null).', returns_tomorrow='.json_encode($fifth->unitReturns)."\n";
    }

    // ── the review day ──────────────────────────────────────────────────────────────────────────────────────────────
    $today = app(LearnerCalendar::class)->todayFor($actor, app(Clock::class)->now());
    DB::table('plan_days')->where('id', $day1->id)->update(['status' => 'closed', 'closed_at' => now()]);
    DB::table('plan_days')->where('plan_id', $planId)->where('number', 2)->update(['opens_on' => $today->format('Y-m-d')]);
    app(OpenDayHandler::class)(new OpenDay(PlanId::fromString($planId), 2, $actor));
    $day2 = DB::table('plan_days')->where('plan_id', $planId)->where('number', 2)->first();
    $review = $cardsRepo->forDay(PlanDayId::fromString((string) $day2->id));

    echo "\n## День повторения (день 2, вставлен в транзакции)\n\n";
    $stageTable($review);
    echo "\nВернулось:\n";
    foreach ($review as $card) {
        if ($card->source()->value === 'returned') {
            echo '- '.$card->stage()->value.' · '.$describe($card).' · source_day='.((string) DB::table('plan_days')->where('id', $card->sourceDayId()?->value)->value('number'))."\n";
        }
    }
} finally {
    DB::rollBack();
}

$after = $snapshot();
echo "\nпосле отката: день 1 — ".json_encode($after['days'][0] ?? null, JSON_UNESCAPED_UNICODE).", дней ".count($before['days']).' → '.count($after['days']).", карточек {$after['cards']}, отвечено {$after['answered']}\n";
echo 'состояние e2e совпадает с исходным: '.json_encode($after === $before)."\n";
