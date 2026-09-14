<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Blueprint\PlanTitles;
use App\Modules\Plan\Domain\Blueprint\SceneBrief;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Service\DayPace;
use App\Modules\Plan\Domain\Service\DayWindowStages;
use App\Modules\Plan\Domain\Service\ImageQueries;
use App\Modules\Plan\Domain\Service\ImageTones;
use App\Modules\Plan\Domain\Service\UnitStates;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\CardResult;
use App\Modules\Plan\Domain\ValueObject\CardSource;
use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\DayStatus;
use App\Modules\Plan\Domain\ValueObject\Image;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\PlanStatus;
use App\Modules\Plan\Domain\ValueObject\PlanTermId;
use App\Modules\Plan\Domain\ValueObject\ProgramSummary;
use App\Modules\Plan\Domain\ValueObject\SceneKind;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\TermKind;
use App\Modules\Plan\Domain\ValueObject\UnitKind;
use App\Modules\Plan\Domain\ValueObject\UnitState;
use App\Modules\Plan\Domain\ValueObject\WindowAction;
use App\Modules\Plan\Domain\ValueObject\WindowStage;
use App\Modules\Plan\Domain\ValueObject\WindowStatus;

/**
 * THE DAY WINDOW'S RULES (DAY-UI-2, кадры 23-0a…0c) — pure: statuses, stage rows, minutes, unit
 * states, tones and the photo ladder. The HTTP side is `tests/Feature/Plan/PlanDayWindowTest.php`.
 */

/** A card of scene S1; `answered` passes it, `returns` fails it twice. */
function windowCard(Stage $stage, CardKind $kind, UnitKind $unit, string $ref, bool $answered = false, bool $returns = false): DayCard
{
    $card = DayCard::dealt(DayCardId::generate(), PlanDayId::generate(), $stage, 1, $kind, ['scene_id' => 'S1'], CardSource::Today, null, $unit, $ref);
    if ($returns) {
        $retry = $card->retry(DayCardId::generate(), 2);
        $retry->answer(CardResult::Failed, 2, new DateTimeImmutable);

        return $retry;
    }
    if ($answered) {
        $card->answer(CardResult::Passed, 1, new DateTimeImmutable);
    }

    return $card;
}

/**
 * A day: `$stages` maps a stage to [cards, answered].
 *
 * @param  array<string, array{0: int, 1: int}>  $stages
 * @return list<DayCard>
 */
function windowDay(array $stages): array
{
    $kinds = ['words' => CardKind::WordIntro, 'phrases' => CardKind::PhraseIntro, 'dialogue' => CardKind::DialogueRead, 'listen' => CardKind::ListenQuestion, 'speak' => CardKind::Speak];
    $cards = [];
    foreach ($stages as $stage => [$total, $answered]) {
        for ($i = 0; $i < $total; $i++) {
            $cards[] = windowCard(Stage::from($stage), $kinds[$stage], UnitKind::Word, "{$stage}{$i}", $i < $answered);
        }
    }

    return $cards;
}

/** @param list<WindowStage> $rows @return list<array{0: string, 1: string, 2: int|null, 3: int|null}> */
function windowRows(array $rows): array
{
    return array_map(static fn (WindowStage $s): array => [$s->stage->value, $s->state->value, $s->doneCount, $s->total], $rows);
}

const WINDOW_FULL_DAY = ['words' => [32, 0], 'phrases' => [18, 0], 'dialogue' => [1, 0], 'listen' => [16, 0], 'speak' => [8, 0]];

it('reads the day in one of three words — day one of a built, unstarted plan is «не начат», any other locked day is refused', function () {
    expect(WindowStatus::of(DayStatus::Open, PlanStatus::Active, 2))->toBe(WindowStatus::NotStarted)
        ->and(WindowStatus::of(DayStatus::InProgress, PlanStatus::Active, 2))->toBe(WindowStatus::InProgress)
        ->and(WindowStatus::of(DayStatus::Closed, PlanStatus::Finished, 2))->toBe(WindowStatus::Passed)
        ->and(WindowStatus::of(DayStatus::Locked, PlanStatus::Ready, 1))->toBe(WindowStatus::NotStarted)
        ->and(WindowStatus::of(DayStatus::Locked, PlanStatus::Ready, 2))->toBe(WindowStatus::Locked)
        ->and(WindowStatus::of(DayStatus::Locked, PlanStatus::Active, 3))->toBe(WindowStatus::Locked);
});

it('has one action per status — «Ещё раз» only for a passed day that has something to say aloud', function () {
    expect(WindowStatus::NotStarted->action(true))->toBe(WindowAction::Start)
        ->and(WindowStatus::InProgress->action(true))->toBe(WindowAction::Continue)
        ->and(WindowStatus::Passed->action(true))->toBe(WindowAction::Again)
        ->and(WindowStatus::Passed->action(false))->toBeNull()
        ->and(WindowStatus::Locked->action(true))->toBeNull();
});

it('prints no number on a day not started — every row «впереди», the first one too (23-0a)', function () {
    $rows = DayWindowStages::of(windowDay(WINDOW_FULL_DAY), [], WindowStatus::NotStarted);

    expect(windowRows($rows))->toBe([
        ['words', 'locked', null, null], ['phrases', 'locked', null, null], ['dialogue', 'locked', null, null],
        ['listen', 'locked', null, null], ['speak', 'locked', null, null],
    ])->and(array_map(static fn (WindowStage $s): float => $s->share, $rows))->toBe([0.0, 0.0, 0.0, 0.0, 0.0]);
});

it('puts the count, the minutes left and a partial bar on the current row only — catches a number on every row (23-0b)', function () {
    $rows = DayWindowStages::of(windowDay(['words' => [32, 32], 'phrases' => [18, 18], 'dialogue' => [1, 1], 'listen' => [16, 6], 'speak' => [8, 0]]), [], WindowStatus::InProgress);

    expect(windowRows($rows))->toBe([
        ['words', 'done', null, null], ['phrases', 'done', null, null], ['dialogue', 'done', null, null],
        ['listen', 'current', 6, 16], ['speak', 'locked', null, null],
    ])
        ->and($rows[3]->share)->toBe(0.38)
        ->and($rows[3]->minutesLeft)->toBe(DayPace::minutes(10 * 13))
        ->and($rows[0]->share)->toBe(1.0)
        ->and($rows[4]->share)->toBe(0.0)
        ->and(DayWindowStages::progress($rows))->toBe(0.6);
});

it('fills every row of a passed day and prints no number on any (23-0c)', function () {
    $rows = DayWindowStages::of(windowDay(WINDOW_FULL_DAY), [], WindowStatus::Passed);

    expect(array_unique(array_map(static fn (WindowStage $s): string => $s->state->value, $rows)))->toBe(['done'])
        ->and(array_filter(array_map(static fn (WindowStage $s): ?int => $s->doneCount ?? $s->total ?? $s->minutesLeft, $rows)))->toBe([])
        ->and(DayWindowStages::progress($rows))->toBe(1.0);
});

it('rows a day with no cards yet by what its type deals — nothing walked, nothing counted', function () {
    $rows = DayWindowStages::of([], [Stage::Words, Stage::Speak], WindowStatus::NotStarted);

    expect(windowRows($rows))->toBe([['words', 'locked', null, null], ['speak', 'locked', null, null]])
        ->and(DayWindowStages::minutesEstimate([], WindowStatus::NotStarted))->toBeNull();
});

it('estimates the day by the pace of its stages: all of it before the start, what is left while walked, nothing once passed', function () {
    $fresh = windowDay(WINDOW_FULL_DAY);
    $walked = windowDay(['words' => [32, 32], 'phrases' => [18, 18], 'dialogue' => [1, 1], 'listen' => [16, 6], 'speak' => [8, 0]]);

    expect(DayWindowStages::minutesEstimate($fresh, WindowStatus::NotStarted))->toBe(DayPace::minutes(32 * 8 + 18 * 29 + 34 + 16 * 13 + 8 * 41))
        ->and(DayWindowStages::minutesEstimate($walked, WindowStatus::InProgress))->toBe(DayPace::minutes(10 * 13 + 8 * 41))
        ->and(DayWindowStages::minutesEstimate($walked, WindowStatus::Passed))->toBeNull()
        ->and(DayPace::minutes(1))->toBe(1)
        ->and(DayPace::minutes(0))->toBe(0);
});

it('reads a unit over all its cards: a second failure returns it, all answered walks it — and the dialogue read walks no exchange', function () {
    $cards = [
        windowCard(Stage::Words, CardKind::WordIntro, UnitKind::Word, 'v1', answered: true),
        windowCard(Stage::Words, CardKind::WordChoose, UnitKind::Word, 'v1', answered: true),
        windowCard(Stage::Words, CardKind::WordIntro, UnitKind::Word, 'v2', answered: true),
        windowCard(Stage::Words, CardKind::WordChoose, UnitKind::Word, 'v2', returns: true),
        windowCard(Stage::Words, CardKind::WordIntro, UnitKind::Word, 'v3', answered: true),
        windowCard(Stage::Words, CardKind::WordChoose, UnitKind::Word, 'v3'),
        windowCard(Stage::Dialogue, CardKind::DialogueRead, UnitKind::Exchange, 'x1', answered: true),
        windowCard(Stage::Speak, CardKind::Speak, UnitKind::Exchange, 'x1'),
    ];
    $states = UnitStates::of($cards);

    expect($states[UnitStates::key('S1', UnitKind::Word, 'v1')])->toBe(UnitState::Done)
        ->and($states[UnitStates::key('S1', UnitKind::Word, 'v2')])->toBe(UnitState::ReturnsTomorrow)
        ->and($states[UnitStates::key('S1', UnitKind::Word, 'v3')])->toBe(UnitState::Pending)
        ->and($states[UnitStates::key('S1', UnitKind::Exchange, 'x1')])->toBe(UnitState::Pending)
        ->and(ProgramSummary::of(array_values($states)))->toEqual(new ProgramSummary(4, 1, 1));
});

it('paints a slot with the first tone it knows, and with the theme’s empty slot when it knows none', function () {
    expect(ImageTones::first(null, '#978e82', '#111111'))->toBe('#978E82')
        ->and(ImageTones::first('not a tone', null))->toBe(ImageTones::THEME)
        ->and(ImageTones::first())->toBe('#E3DCCF');
});

it('asks for a photo by the description, then the word alone, then the scene’s title — catches a word without a description never searched', function () {
    $scene = PlanScene::fromBrief(PlanSceneId::generate(), PlanId::generate(), new SceneBrief(
        1, SceneKind::Situation, 1, 'Приём у врача', 'At the doctor’s', 'описать боль', ['описать боль'],
        'Patient', 'Пациент', 'Doctor', 'Врач', 'Situation: …', 'doctor’s office with a patient',
    ));
    $term = static fn (?string $prompt, string $text): PlanTerm => PlanTerm::reconstitute(
        PlanTermId::generate(), $scene->id(), TermKind::Word, 'v1', 0, $text, 'перевод', null, null, null, null, null, [], $prompt, null,
    );
    $titles = new PlanTitles('Врач', 'Doctor', 'Приём', 'До приёма', 'Приём был', 'clinic corridor', 'Patient', 'Пациент');

    expect(ImageQueries::forTerm($term('a thermometer', 'fever'), $scene))->toBe(['a thermometer', 'fever', 'At the doctor’s'])
        ->and(ImageQueries::forTerm($term(null, 'in progress'), $scene))->toBe(['in progress', 'At the doctor’s'])
        ->and(ImageQueries::forTerm($term('  ', 'At the doctor’s'), $scene))->toBe(['At the doctor’s'])
        ->and(ImageQueries::forScene($scene, $titles))->toBe(['doctor’s office with a patient', 'At the doctor’s', 'clinic corridor']);
});

it('asks the ladder once for a word: a found-nothing tone marks it asked, a photo clears the mark, a phrase is never asked', function () {
    $sceneId = PlanSceneId::generate();
    $word = PlanTerm::reconstitute(PlanTermId::generate(), $sceneId, TermKind::Word, 'v1', 0, 'scope', 'объём', null, null, null, null, null, [], null, null);
    $phrase = PlanTerm::reconstitute(PlanTermId::generate(), $sceneId, TermKind::Phrase, 'p1', 1, 'It hurts.', 'Болит.', null, null, null, null, null, [], null, null);

    expect($word->needsImage())->toBeTrue()->and($phrase->needsImage())->toBeFalse();
    $word->markImageMissing('#978E82');
    expect($word->needsImage())->toBeFalse()->and($word->imageTone())->toBe('#978E82')->and($word->image())->toBeNull();

    $word->attachImage(new Image('https://images.pexels.test/scope.jpg', null, null, '#223344'));
    expect($word->imageTone())->toBe('#223344')->and($word->needsImage())->toBeFalse();
});
