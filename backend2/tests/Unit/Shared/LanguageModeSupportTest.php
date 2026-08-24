<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\ValueObject\EnabledModes;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\Service\ModePassport;
use App\Modules\Shared\Domain\Service\LanguageModeSupport;

/**
 * The language half of the mode gate (DECISIONS п. 130): a table in code, and the ONE intersection
 * that reads it ({@see EnabledModes::forLanguage()}).
 */
it('names every language the capability matrix teaches, plus the two reference ones', function () {
    // The seven taught languages (DECISIONS п. 83) and zh/ja (п. 84). A taught language missing from
    // the table would carry NO trainers at all — silently, which is the failure this pins.
    expect(LanguageModeSupport::languages())
        ->toEqualCanonicalizing(['en', 'de', 'es', 'it', 'fr', 'pl', 'ro', 'zh', 'ja']);
});

it('speaks the same mode vocabulary as the ExerciseMode enum', function () {
    // The table holds strings because Shared\Domain imports no module. This is what keeps the two
    // vocabularies from drifting when a trainer is renamed or added.
    expect(LanguageModeSupport::allModes())
        ->toEqualCanonicalizing(array_map(static fn (ExerciseMode $m): string => $m->value, ExerciseMode::cases()));
});

it('gives English the full set — it is the language every gate was written for', function () {
    expect(LanguageModeSupport::modesFor('en'))
        ->toEqualCanonicalizing(LanguageModeSupport::allModes());
});

it('gives the reference languages no trainers at all', function () {
    expect(LanguageModeSupport::modesFor('zh'))->toBe([])
        ->and(LanguageModeSupport::modesFor('ja'))->toBe([]);
});

it('gives a language this product does not teach no trainers either', function () {
    // `ru` and `uk` are SUPPORT languages (п. 85), not taught ones — a term in them has no
    // strictness rules and no grader written for it.
    expect(LanguageModeSupport::modesFor('ru'))->toBe([])
        ->and(LanguageModeSupport::modesFor('sv'))->toBe([]);
});

it('closes pick_correct everywhere but English, and closes nothing else', function () {
    foreach (['de', 'es', 'it', 'fr', 'pl', 'ro'] as $lang) {
        expect(LanguageModeSupport::supports($lang, 'pick_correct'))->toBeFalse()
            ->and(LanguageModeSupport::modesFor($lang))
            ->toEqualCanonicalizing(array_values(array_diff(LanguageModeSupport::allModes(), ['pick_correct'])));
    }
});

it('marks speaking and dictation online-only for pl and ro — available, not absent', function () {
    foreach (['pl', 'ro'] as $lang) {
        expect(LanguageModeSupport::supports($lang, 'speaking'))->toBeTrue()
            ->and(LanguageModeSupport::isOnlineOnly($lang, 'speaking'))->toBeTrue()
            ->and(LanguageModeSupport::isOnlineOnly($lang, 'dictation'))->toBeTrue()
            // …and only those two: typing is offline like everywhere else.
            ->and(LanguageModeSupport::isOnlineOnly($lang, 'typing'))->toBeFalse();
    }

    expect(LanguageModeSupport::isOnlineOnly('en', 'speaking'))->toBeFalse();
});

it('never reports a CLOSED mode as merely online-only', function () {
    // The two answers have opposite meanings for the client: one hides the trainer, the other warns
    // about the network. Collapsing them would promise a Polish `pick_correct` «as soon as you are
    // online».
    expect(LanguageModeSupport::isOnlineOnly('pl', 'pick_correct'))->toBeFalse()
        ->and(LanguageModeSupport::isOnlineOnly('zh', 'speaking'))->toBeFalse();
});

// ── the intersection ─────────────────────────────────────────────────────────

it('intersects the product matrix with the language, and can only remove', function () {
    $all = new EnabledModes(ExerciseMode::cases());

    $polish = $all->forLanguage('pl');

    expect($polish)->not->toBeNull()
        ->and($polish->has(ExerciseMode::PickCorrect))->toBeFalse()
        // …and everything the language DOES carry survives the intersection untouched.
        ->and($polish->has(ExerciseMode::Typing))->toBeTrue()
        ->and($polish->has(ExerciseMode::Speaking))->toBeTrue()
        ->and($polish->has(ExerciseMode::Dictation))->toBeTrue();
});

it('cannot OPEN a mode the product has switched off', function () {
    // The gate points one way: the panel closes a trainer, the language cannot re-open it.
    $off = new EnabledModes([ExerciseMode::Typing]);

    expect($off->forLanguage('en')?->modes)->toBe([ExerciseMode::Typing]);
});

it('answers null for a language that carries no trainer, rather than an empty set', function () {
    $all = new EnabledModes(ExerciseMode::cases());

    expect($all->forLanguage('zh'))->toBeNull()
        ->and($all->forLanguage('ja'))->toBeNull();
});

it('falls to the floor when the language CAN train but none of the switched-on modes fit', function () {
    // A configuration, not a capability — and configuration gets the same answer it always gets in
    // this app: a playable card beats an empty session (ExerciseSelector::floor()).
    $onlyPickCorrect = new EnabledModes([ExerciseMode::PickCorrect]);

    expect($onlyPickCorrect->forLanguage('pl')?->modes)->toBe([ExerciseMode::MultipleChoice])
        // …and a language that trains nothing still answers null: there is no honest floor there.
        ->and($onlyPickCorrect->forLanguage('zh'))->toBeNull();
});

// ── the reason, kept apart from «closed by the matrix» ────────────────────────

it('states «closed by language» separately from «closed by the matrix»', function () {
    expect(ModePassport::closedByLanguage(ExerciseMode::PickCorrect, 'pl'))->toBeTrue()
        ->and(ModePassport::closedByLanguage(ExerciseMode::PickCorrect, 'en'))->toBeFalse()
        // Two different sentences for two different cures: a threshold on an admin screen versus a
        // distractor judge that does not exist for this language yet.
        ->and(ModePassport::languageReasonFor(ExerciseMode::PickCorrect, 'pl'))
        ->not->toBe(ModePassport::reasonFor(ExerciseMode::PickCorrect))
        ->and(ModePassport::languageReasonFor(ExerciseMode::PickCorrect, 'pl'))
        ->toContain('контроль качества дистракторов');
});

it('explains a reference language as «not trained», not as «this trainer is missing»', function () {
    expect(ModePassport::languageReasonFor(ExerciseMode::Typing, 'zh'))->toContain('справочная');
});
