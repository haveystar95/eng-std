<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Service\DayHighlights;
use App\Modules\Plan\Domain\Service\NativeStrings;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\CardResult;
use App\Modules\Plan\Domain\ValueObject\CardSource;
use App\Modules\Plan\Domain\ValueObject\ConversationEnd;
use App\Modules\Plan\Domain\ValueObject\ConversationOutcome;
use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\UnitKind;

/**
 * «ЧТО БЫЛО ХОРОШО» OF A REHEARSAL AND OF A REVIEW (наряд FIX-4c §5): «Сказал сам 1 реплику из 9» on the e2e rehearsal
 * was read as the talk's count. It is not, and the rule is right: the rehearsal's own speech cards are its «Вспомнить» —
 * nine retellings of the learner's lines (`speak_retell`) — and a review's its «Повторение» (`speak_answer`), and the
 * first line counts THEM; the talk has lines of its own — the constructions it used, the questions understood. On the
 * stand the nine were answered on 21.09 (1 passed, 8 skipped) and only the talk was walked again on 25.09: a finding of
 * the stand (отчёт FIX-4c §5), not of the rule.
 */

/** A card of the day, answered or not. */
function dhCard(Stage $stage, CardKind $kind, ?CardResult $result, UnitKind $unit = UnitKind::Exchange): DayCard
{
    $card = DayCard::dealt(DayCardId::generate(), PlanDayId::generate(), $stage, 1, $kind, ['scene_id' => 'S1'], CardSource::Today, null, $unit, 'x1');
    if ($result !== null) {
        $card->answer($result, 1, null, new DateTimeImmutable);
    }

    return $card;
}

/** The talk of the day: seven targets of seven said, every question understood, one construction said beyond them. */
function dhTalk(): ConversationOutcome
{
    return new ConversationOutcome(9, ['S1:p1', 'S1:p2', 'S1:p3', 'S1:p4', 'S2:p1', 'S2:p2', 'S2:p3'], 7, [], true, 0, 0, ConversationEnd::Natural, 4, ['S2:p4']);
}

// Canon (§5): «разобрать, что считает DayHighlights для дня без своих карточек речи». A rehearsal HAS its own: the first
// line counts the day's «Вспомнить» — the overview card is no line said —, the talk its own two. CATCHES the talk's
// constructions counted as lines said, the overview counted as one, and a line about the recall that drops the skips.
it('counts a rehearsal\'s own «Вспомнить» in the first line and gives the talk its own lines', function () {
    $cards = [dhCard(Stage::Recall, CardKind::RecallScenes, CardResult::Passed, UnitKind::Day)];
    $cards[] = dhCard(Stage::Recall, CardKind::SpeakRetell, CardResult::Passed);
    for ($i = 0; $i < 8; $i++) {
        $cards[] = dhCard(Stage::Recall, CardKind::SpeakRetell, CardResult::Skipped);
    }

    expect(DayHighlights::of($cards, dhTalk(), new NativeStrings('ru')))->toBe([
        'Сказал сам 1 реплику из 9',
        'В разговоре использовал 7 фраз из 7',
        'Понял все вопросы',
    ]);
});

// Canon (§5): a review counts its «Повторение» (`speak_answer`, a hint counts as said, as on any card); a day whose talk is
// its only walked speech says nothing about lines — no «0 из N» for cards nobody dealt.
it('counts a review\'s «Повторение» and says nothing of lines where no speech card was dealt', function () {
    $review = [
        dhCard(Stage::Repetition, CardKind::SpeakAnswer, CardResult::Hinted),
        dhCard(Stage::Repetition, CardKind::SpeakAnswer, CardResult::Passed),
        dhCard(Stage::Repetition, CardKind::SpeakAnswer, CardResult::Skipped),
    ];

    expect(DayHighlights::of($review, dhTalk(), new NativeStrings('ru'))[0])->toBe('Сказал сам 2 реплики из 3')
        ->and(DayHighlights::of([], dhTalk(), new NativeStrings('ru')))->toBe(['В разговоре использовал 7 фраз из 7', 'Понял все вопросы']);
});
