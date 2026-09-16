<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Service\DayPace;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\CardResult;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\UnitKind;

/**
 * THE REGISTRY OF DAY TRAINERS (наряд SESSION-1a, разд. 1; D-01, D-05, D-31): twenty-nine kinds, twenty-eight dealt,
 * each in one stage and counted one way — which is what the server lets the client write for it.
 */

it('has the twenty-nine kinds of the registry in its order, twenty-eight of them dealt — listen_pairs only reserved', function () {
    expect(array_map(static fn (CardKind $k): string => $k->value, CardKind::cases()))->toBe([
        'word_intro', 'word_repeat', 'word_choose', 'word_listen', 'word_assemble', 'word_in_line',
        'phrase_intro', 'phrase_assemble', 'phrase_choose_back', 'phrase_slot', 'phrase_slot_listen', 'phrase_repeat',
        'phrase_other_slot', 'phrase_combine', 'phrase_own_slot',
        'dialogue_partner', 'dialogue_answer', 'dialogue_ask', 'dialogue_rescue',
        'listen_dialogue', 'listen_question', 'listen_review', 'listen_pairs', 'listen_predict', 'listen_pace', 'listen_number',
        'speak_answer', 'speak_echo', 'speak_retell',
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
        };
        expect($kind->stage())->toBe($expected, $kind->value);
    }
});

// Catches a kind added to two lists (a choice that is also spoken) or to none (a kind nobody can answer).
it('counts every kind exactly one way: choice, spoken, judged or walkthrough', function () {
    foreach (CardKind::cases() as $kind) {
        $ways = array_filter([$kind->isChoice(), $kind->isSpoken(), $kind->isJudged(), $kind->isWalkthrough()]);
        expect($ways)->toHaveCount(1, $kind->value);
    }

    expect(array_values(array_filter(CardKind::cases(), static fn (CardKind $k): bool => $k->isJudged())))
        ->toBe([CardKind::PhraseOwnSlot, CardKind::SpeakAnswer, CardKind::SpeakRetell])
        ->and(array_values(array_filter(CardKind::cases(), static fn (CardKind $k): bool => $k->isSpoken())))
        ->toBe([CardKind::WordRepeat, CardKind::PhraseRepeat, CardKind::PhraseOtherSlot, CardKind::DialogueAnswer, CardKind::DialogueAsk, CardKind::SpeakEcho])
        ->and(array_values(array_filter(CardKind::cases(), static fn (CardKind $k): bool => $k->isWalkthrough())))
        ->toBe([CardKind::WordIntro, CardKind::PhraseIntro, CardKind::DialogueRescue, CardKind::ListenDialogue, CardKind::ListenReview, CardKind::ListenPace]);
});

// D-31: a judged card is passed only by the judge; a walkthrough is never right or wrong; the voice never fails.
it('lets the client write only the results the kind can have', function () {
    $allowed = static fn (CardKind $kind): array => array_values(array_map(
        static fn (CardResult $r): string => $r->value,
        array_filter(CardResult::cases(), static fn (CardResult $r): bool => $kind->allows($r)),
    ));

    expect($allowed(CardKind::SpeakAnswer))->toBe(['skipped'])
        ->and($allowed(CardKind::PhraseOwnSlot))->toBe(['skipped'])
        ->and($allowed(CardKind::SpeakRetell))->toBe(['skipped'])
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

it('returns a word as word_choose, a frame as phrase_slot, an exchange as speak_answer — and never the day', function () {
    expect(CardKind::returnedFor(UnitKind::Word))->toBe(CardKind::WordChoose)
        ->and(CardKind::returnedFor(UnitKind::Phrase))->toBe(CardKind::PhraseSlot)
        ->and(CardKind::returnedFor(UnitKind::Exchange))->toBe(CardKind::SpeakAnswer)
        ->and(CardKind::returnedFor(UnitKind::Day))->toBeNull()
        ->and(UnitKind::Day->returns())->toBeFalse()
        ->and(UnitKind::Word->returns())->toBeTrue()
        ->and(UnitKind::Phrase->returns())->toBeTrue()
        ->and(UnitKind::Exchange->returns())->toBeTrue();
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
