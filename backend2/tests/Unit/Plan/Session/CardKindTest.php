<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Service\DayPace;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\CardResult;
use App\Modules\Plan\Domain\ValueObject\DayType;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\UnitKind;

/**
 * THE REGISTRY OF DAY TRAINERS (наряд SESSION-1a, разд. 1; D-01, D-05, D-31; наряды FIX-2 п. 5 и CONV-1):
 * twenty-nine kinds, twenty-eight dealt, each in one stage and counted one way — which is what the server lets the
 * client write for it.
 */

it('has the twenty-nine kinds of the registry in its order, twenty-eight of them dealt — listen_pairs only reserved', function () {
    expect(array_map(static fn (CardKind $k): string => $k->value, CardKind::cases()))->toBe([
        'word_intro', 'word_repeat', 'word_choose', 'word_listen', 'word_assemble', 'word_in_line',
        'phrase_intro', 'phrase_assemble', 'phrase_choose_back', 'phrase_slot', 'phrase_slot_listen', 'phrase_repeat',
        'phrase_other_slot', 'phrase_combine',
        'dialogue_partner', 'dialogue_answer', 'dialogue_ask', 'dialogue_rescue',
        'listen_dialogue', 'listen_question', 'listen_review', 'listen_pairs', 'listen_predict', 'listen_pace', 'listen_number',
        'speak_answer', 'speak_echo', 'speak_retell',
        'recall_scenes',
    ])
        ->and(CardKind::dealt())->toHaveCount(28)
        ->and(CardKind::dealt())->not->toContain(CardKind::ListenPairs)
        ->and(CardKind::ListenPairs->isDealt())->toBeFalse();
});

it('puts every kind into the stage its name says', function () {
    foreach (CardKind::cases() as $kind) {
        $expected = match (explode('_', $kind->value)[0]) {
            'word' => Stage::Words,
            'phrase' => Stage::Phrases,
            'dialogue' => Stage::Dialogue,
            'listen' => Stage::Listen,
            'speak' => Stage::Speak,
            'recall' => Stage::Recall,
        };
        expect($kind->stage())->toBe($expected, $kind->value);
    }
});

/**
 * ONE KIND READS ITS STAGE OFF THE DAY TOO (наряд CONV-1): «Повтори свою реплику» is «Говорю сам» on a scene day and
 * «Вспомнить» on the rehearsal — the same trainer, the same screen (кадр 35-4), two places. The defect this catches is
 * a rehearsal that deals its lines into «Говорю сам» and draws a stage the day does not have.
 */
it('moves only speak_retell to «Вспомнить», and only on the rehearsal', function () {
    foreach (CardKind::cases() as $kind) {
        $moved = $kind->stage(DayType::Rehearsal) !== $kind->stage();
        expect($moved)->toBe($kind === CardKind::SpeakRetell, $kind->value);
    }

    expect(CardKind::SpeakRetell->stage(DayType::Rehearsal))->toBe(Stage::Recall)
        ->and(CardKind::SpeakRetell->stage(DayType::Scene))->toBe(Stage::Speak);
});

// Canon (наряд BACK-TAILS-2 §3): «день повторения отдаёт ряд своих карточек с id repetition, не speak». CATCHES a review
// card read as «Говорю сам», and a kind of another stage pulled into the repetition.
it('moves the kinds of «Говорю сам» to «Повторение» on a review day, and nothing else', function () {
    foreach (CardKind::cases() as $kind) {
        $moved = $kind->stage(DayType::Review) !== $kind->stage();
        expect($moved)->toBe(in_array($kind, [CardKind::SpeakAnswer, CardKind::SpeakEcho, CardKind::SpeakRetell], true), $kind->value);
    }

    expect(CardKind::SpeakAnswer->stage(DayType::Review))->toBe(Stage::Repetition)
        ->and(CardKind::SpeakRetell->stage(DayType::Review))->toBe(Stage::Repetition)
        ->and(CardKind::SpeakAnswer->stage(DayType::Scene))->toBe(Stage::Speak)
        ->and(CardKind::WordChoose->stage(DayType::Review))->toBe(Stage::Words)
        ->and(array_map(static fn (Stage $s): string => $s->value, Stage::ordered()))
        ->toBe(['words', 'phrases', 'dialogue', 'listen', 'speak', 'recall', 'repetition', 'conversation']);
});

// Catches a kind added to two lists (a choice that is also spoken) or to none (a kind nobody can answer).
it('counts every kind exactly one way: choice, spoken, judged or walkthrough', function () {
    foreach (CardKind::cases() as $kind) {
        $ways = array_filter([$kind->isChoice(), $kind->isSpoken(), $kind->isJudged(), $kind->isWalkthrough()]);
        expect($ways)->toHaveCount(1, $kind->value);
    }

    expect(array_values(array_filter(CardKind::cases(), static fn (CardKind $k): bool => $k->isJudged())))
        ->toBe([CardKind::SpeakAnswer])
        ->and(array_values(array_filter(CardKind::cases(), static fn (CardKind $k): bool => $k->isSpoken())))
        ->toBe([CardKind::WordRepeat, CardKind::PhraseRepeat, CardKind::PhraseOtherSlot, CardKind::DialogueAnswer, CardKind::DialogueAsk, CardKind::SpeakEcho, CardKind::SpeakRetell])
        ->and(array_values(array_filter(CardKind::cases(), static fn (CardKind $k): bool => $k->isWalkthrough())))
        ->toBe([CardKind::WordIntro, CardKind::PhraseIntro, CardKind::DialogueRescue, CardKind::ListenDialogue, CardKind::ListenReview, CardKind::ListenPace, CardKind::RecallScenes]);
});

// D-31: a judged card is passed only by the judge; a walkthrough is never right or wrong; the voice never fails.
it('lets the client write only the results the kind can have', function () {
    $allowed = static fn (CardKind $kind): array => array_values(array_map(
        static fn (CardResult $r): string => $r->value,
        array_filter(CardResult::cases(), static fn (CardResult $r): bool => $kind->allows($r)),
    ));

    expect($allowed(CardKind::SpeakAnswer))->toBe(['skipped'])
        // «Скажи целиком» ASKS the judge and is not judged BY it (наряд FIX-2, п. 5): the own-word round is practice,
        // and the card's result is its value rounds', written by the client like any voice card's.
        ->and($allowed(CardKind::PhraseOtherSlot))->toBe(['passed', 'hinted', 'skipped'])
        // «Повтори свою реплику» is a voice card since наряд BACK-TAILS-1 §1.1: the client counts its coverage.
        ->and($allowed(CardKind::SpeakRetell))->toBe(['passed', 'hinted', 'skipped'])
        ->and($allowed(CardKind::ListenPace))->toBe(['passed', 'skipped'])
        ->and($allowed(CardKind::DialogueRescue))->toBe(['passed', 'skipped'])
        ->and($allowed(CardKind::WordIntro))->toBe(['passed', 'skipped'])
        ->and($allowed(CardKind::WordRepeat))->toBe(['passed', 'hinted', 'skipped'])
        ->and($allowed(CardKind::DialogueAnswer))->toBe(['passed', 'hinted', 'skipped'])
        ->and($allowed(CardKind::SpeakEcho))->toBe(['passed', 'hinted', 'skipped'])
        ->and($allowed(CardKind::WordChoose))->toBe(['passed', 'hinted', 'failed', 'skipped'])
        ->and($allowed(CardKind::ListenNumber))->toBe(['passed', 'hinted', 'failed', 'skipped']);

    foreach (CardKind::cases() as $kind) {
        expect($kind->allows(CardResult::Skipped))->toBeTrue($kind->value)
            ->and($kind->allows(CardResult::Failed))->toBe($kind->isChoice(), $kind->value);
    }
});

it('returns a word, a frame and an exchange — never the day', function () {
    expect(UnitKind::Day->returns())->toBeFalse()
        ->and(UnitKind::Word->returns())->toBeTrue()
        ->and(UnitKind::Phrase->returns())->toBeTrue()
        ->and(UnitKind::Exchange->returns())->toBeTrue();
});

// Canon (SESSION-1d, DECISIONS п. 327): «провал произнесения = skipped с attempts ≥ 2 и без no_mic — только phrase_repeat и
// phrase_other_slot; «Пропустить» до второй попытки и отказ микрофона — без последствий». Catches any skip counted as a
// lapse, a lapse without the second attempt, a dead microphone counted against the learner, and the rule spread to the
// other voice cards. Наряд BACK-TAILS-1, доработка §1: on `dialogue_ask` the lapse is the choice and nothing else.
it('counts a lapse: a wrong choice, or a phrase said aloud given up on after two attempts with a microphone — nothing else', function () {
    foreach ([CardKind::PhraseRepeat, CardKind::PhraseOtherSlot] as $kind) {
        expect($kind->lapses(CardResult::Skipped, 2, false))->toBeTrue($kind->value)
            ->and($kind->lapses(CardResult::Skipped, 3, false))->toBeTrue($kind->value)
            ->and($kind->lapses(CardResult::Skipped, 1, false))->toBeFalse($kind->value)
            ->and($kind->lapses(CardResult::Skipped, 2, true))->toBeFalse($kind->value)
            ->and($kind->lapses(CardResult::Passed, 2, false))->toBeFalse($kind->value)
            ->and($kind->lapses(CardResult::Hinted, 2, false))->toBeFalse($kind->value)
            ->and($kind->requeues())->toBeTrue($kind->value)
            // The voice still never writes `failed` (422): a lapse of the voice is a skip.
            ->and($kind->allows(CardResult::Failed))->toBeFalse($kind->value);
    }
    foreach ([CardKind::WordRepeat, CardKind::DialogueAnswer, CardKind::SpeakEcho, CardKind::SpeakAnswer, CardKind::SpeakRetell] as $kind) {
        expect($kind->lapses(CardResult::Skipped, 2, false))->toBeFalse($kind->value)
            ->and($kind->requeues())->toBeFalse($kind->value);
    }
    // `dialogue_ask` carries the check of its exchange (наряд BACK-TAILS-1 §1.5), so the ONE thing that lapses on it is
    // the CHOICE — never its voice, whatever the voice did, and never a choice that was right or was not sent.
    expect(CardKind::DialogueAsk->lapses(CardResult::Skipped, 2, false))->toBeFalse()
        ->and(CardKind::DialogueAsk->lapses(CardResult::Passed, 1, false, false))->toBeTrue()
        ->and(CardKind::DialogueAsk->lapses(CardResult::Skipped, 2, false, false))->toBeTrue()
        ->and(CardKind::DialogueAsk->lapses(CardResult::Skipped, 2, false, true))->toBeFalse()
        ->and(CardKind::DialogueAsk->requeues())->toBeTrue()
        ->and(CardKind::DialogueAsk->hasChoice())->toBeTrue()
        ->and(CardKind::DialogueAsk->allows(CardResult::Failed))->toBeFalse()
        // No other kind carries one: a choice sent to them is refused, not judged.
        ->and(array_values(array_filter(CardKind::cases(), static fn (CardKind $k): bool => $k->hasChoice())))->toBe([CardKind::DialogueAsk]);
    expect(CardKind::WordChoose->lapses(CardResult::Failed, 1, false))->toBeTrue()
        ->and(CardKind::WordChoose->lapses(CardResult::Skipped, 2, false))->toBeFalse()
        ->and(CardKind::PhraseSlot->lapses(CardResult::Failed, 1, true))->toBeTrue()
        ->and(CardKind::ListenQuestion->lapses(CardResult::Failed, 1, false))->toBeTrue()
        ->and(CardKind::ListenQuestion->requeues())->toBeFalse()
        ->and(CardKind::PhraseIntro->lapses(CardResult::Skipped, 2, false))->toBeFalse();
});

// Catches a dealt kind the window would price at 0 s, and a pace table in config drifting from the code's defaults.
it('prices every dealt kind and nothing else, the same in the code and in config/plan.php', function () {
    $dealt = array_map(static fn (CardKind $k): string => $k->value, CardKind::dealt());
    $pace = new DayPace;

    expect(array_keys(DayPace::DEFAULTS))->toBe($dealt);
    foreach (CardKind::dealt() as $kind) {
        expect($pace->seconds($kind))->toBeGreaterThan(0, $kind->value);
    }
    expect($pace->seconds(CardKind::ListenPairs))->toBe(0)
        ->and(DayPace::DEFAULTS['listen_dialogue'])->toBe(110)
        ->and(DayPace::DEFAULTS['speak_answer'])->toBe(35)
        ->and(DayPace::DEFAULTS['word_intro'])->toBe(8)
        ->and((require dirname(__DIR__, 4).'/config/plan.php')['pace'])->toBe(DayPace::DEFAULTS);
});

// Canon (наряд FIX-2, п. 5): «последний круг „со своим словом" судит сервер, как у own_slot» — and only there. Catches
// the judge opened to a kind that has no window to rule on, and the two halves of judging confused: who may ASK the
// judge is not who is PASSED by it.
it('opens the judge to speak_answer and «Скажи целиком» only, and lets the verdict pass speak_answer alone', function () {
    expect(array_values(array_filter(CardKind::cases(), static fn (CardKind $k): bool => $k->asksJudge())))
        ->toBe([CardKind::PhraseOtherSlot, CardKind::SpeakAnswer])
        ->and(CardKind::PhraseOtherSlot->isJudged())->toBeFalse()
        ->and(CardKind::SpeakAnswer->isJudged())->toBeTrue();

    foreach (CardKind::cases() as $kind) {
        expect($kind->asksJudge())->toBe($kind->isJudged() || $kind === CardKind::PhraseOtherSlot, $kind->value);
    }
});
