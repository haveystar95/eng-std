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
use App\Modules\Plan\Domain\ValueObject\ImageQuery;
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
 * states, tones and the photo ladder, over the cards of the registry (наряд SESSION-1a). The HTTP side
 * is `tests/Feature/Plan/PlanDayWindowTest.php`; the pace by kind, the day's listening outside the
 * programme and the day a unit returns on — `tests/Unit/Plan/Session/SessionWindowTest.php`.
 */

/** A card of scene S1; `answered` passes it, `returns` fails it twice. */
function windowCard(Stage $stage, CardKind $kind, UnitKind $unit, string $ref, bool $answered = false, bool $returns = false): DayCard
{
    $card = DayCard::dealt(DayCardId::generate(), PlanDayId::generate(), $stage, 1, $kind, ['scene_id' => 'S1'], CardSource::Today, null, $unit, $ref);
    if ($returns) {
        $retry = $card->retry(DayCardId::generate(), 2, $card->payload());
        $retry->answer(CardResult::Failed, 2, null, new DateTimeImmutable);

        return $retry;
    }
    if ($answered) {
        $card->answer(CardResult::Passed, 1, null, new DateTimeImmutable);
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
    $kinds = ['words' => CardKind::WordIntro, 'phrases' => CardKind::PhraseIntro, 'dialogue' => CardKind::DialoguePartner, 'listen' => CardKind::ListenQuestion, 'speak' => CardKind::SpeakAnswer];
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

/** The clean lesson's day of the registry (наряд SESSION-1a, разд. 2): 24 word, 19 phrase, 15 dialogue, 9 listen, 8 speak cards. */
const WINDOW_FULL_DAY = ['words' => [24, 0], 'phrases' => [19, 0], 'dialogue' => [15, 0], 'listen' => [9, 0], 'speak' => [8, 0]];

/** The same day walked into its listening: three of its nine cards answered. */
const WINDOW_WALKED_DAY = ['words' => [24, 24], 'phrases' => [19, 19], 'dialogue' => [15, 15], 'listen' => [9, 3], 'speak' => [8, 0]];

it('reads the day in one of three words — day one of a built, unstarted plan is «не начат», any other locked day is refused', function () {
    expect(WindowStatus::of(DayStatus::Open, PlanStatus::Active, 2, false))->toBe(WindowStatus::NotStarted)
        ->and(WindowStatus::of(DayStatus::InProgress, PlanStatus::Active, 2, false))->toBe(WindowStatus::InProgress)
        ->and(WindowStatus::of(DayStatus::Closed, PlanStatus::Finished, 2, false))->toBe(WindowStatus::Passed)
        ->and(WindowStatus::of(DayStatus::Locked, PlanStatus::Ready, 1, false))->toBe(WindowStatus::NotStarted)
        ->and(WindowStatus::of(DayStatus::Locked, PlanStatus::Ready, 2, false))->toBe(WindowStatus::Locked)
        ->and(WindowStatus::of(DayStatus::Locked, PlanStatus::Active, 3, false))->toBe(WindowStatus::Locked)
        // Наряд GEN-3 §11: a day next in line whose lesson is still being written has no button, whatever its date says.
        ->and(WindowStatus::of(DayStatus::Open, PlanStatus::Active, 2, true))->toBe(WindowStatus::Building)
        ->and(WindowStatus::Building->action())->toBeNull();
});

// Canon (наряд FIX-3 §8): «Удалить: … дневной again на итоге» — «Ещё раз» is each stage's row, not the day's. CATCHES a
// passed day that still offers the day's own «again».
it('has one action per status, and none on a passed day — «Ещё раз» is the rows\'', function () {
    expect(WindowStatus::NotStarted->action())->toBe(WindowAction::Start)
        ->and(WindowStatus::InProgress->action())->toBe(WindowAction::Continue)
        ->and(WindowStatus::Passed->action())->toBeNull()
        ->and(WindowStatus::Locked->action())->toBeNull()
        ->and(array_map(static fn (WindowAction $a): string => $a->value, WindowAction::cases()))->toBe(['start', 'continue']);
});

it('prints no number on a day not started — every row «впереди», the first one too (23-0a)', function () {
    $rows = DayWindowStages::of(windowDay(WINDOW_FULL_DAY), [], WindowStatus::NotStarted, new DayPace);

    expect(windowRows($rows))->toBe([
        ['words', 'locked', null, null], ['phrases', 'locked', null, null], ['dialogue', 'locked', null, null],
        ['listen', 'locked', null, null], ['speak', 'locked', null, null],
    ])->and(array_map(static fn (WindowStage $s): float => $s->share, $rows))->toBe([0.0, 0.0, 0.0, 0.0, 0.0]);
});

it('puts the count, the minutes left and a partial bar on the current row only — catches a number on every row (23-0b)', function () {
    $rows = DayWindowStages::of(windowDay(WINDOW_WALKED_DAY), [], WindowStatus::InProgress, new DayPace);

    // Listen: 3 of 9 answered → a share of 0.33; the six left at listen_question's price.
    expect(windowRows($rows))->toBe([
        ['words', 'done', null, null], ['phrases', 'done', null, null], ['dialogue', 'done', null, null],
        ['listen', 'current', 3, 9], ['speak', 'locked', null, null],
    ])
        ->and($rows[3]->share)->toBe(0.33)
        ->and($rows[3]->minutesLeft)->toBe(DayPace::minutes(6 * DayPace::DEFAULTS['listen_question']))
        ->and($rows[0]->share)->toBe(1.0)
        ->and($rows[4]->share)->toBe(0.0)
        ->and(DayWindowStages::progress($rows))->toBe(0.6);
});

it('fills every row of a passed day and prints no number on any (23-0c)', function () {
    $rows = DayWindowStages::of(windowDay(WINDOW_FULL_DAY), [], WindowStatus::Passed, new DayPace);

    expect(array_unique(array_map(static fn (WindowStage $s): string => $s->state->value, $rows)))->toBe(['done'])
        ->and(array_filter(array_map(static fn (WindowStage $s): ?int => $s->doneCount ?? $s->total ?? $s->minutesLeft, $rows)))->toBe([])
        ->and(DayWindowStages::progress($rows))->toBe(1.0);
});

it('rows a day with no cards yet by what its type deals — nothing walked, nothing counted', function () {
    $rows = DayWindowStages::of([], [Stage::Words, Stage::Speak], WindowStatus::NotStarted, new DayPace);

    expect(windowRows($rows))->toBe([['words', 'locked', null, null], ['speak', 'locked', null, null]])
        ->and(DayWindowStages::minutesEstimate([], WindowStatus::NotStarted, new DayPace))->toBeNull();
});

it('estimates the day by the pace of its kinds: all of it before the start, what is left while walked, nothing once passed', function () {
    $fresh = windowDay(WINDOW_FULL_DAY);
    $walked = windowDay(WINDOW_WALKED_DAY);
    $pace = new DayPace;

    // By the price list of the kinds (наряд SESSION-1a, разд. 2; the prices measured on the phone — наряд FIX-3 §2).
    $p = DayPace::DEFAULTS;
    expect(DayWindowStages::minutesEstimate($fresh, WindowStatus::NotStarted, $pace))
        ->toBe(DayPace::minutes(24 * $p['word_intro'] + 19 * $p['phrase_intro'] + 15 * $p['dialogue_partner'] + 9 * $p['listen_question'] + 8 * $p['speak_answer']))
        ->and(DayWindowStages::minutesEstimate($walked, WindowStatus::InProgress, $pace))->toBe(DayPace::minutes(6 * $p['listen_question'] + 8 * $p['speak_answer']))
        ->and(DayWindowStages::minutesEstimate($walked, WindowStatus::Passed, $pace))->toBeNull()
        ->and(DayPace::minutes(1))->toBe(1)
        ->and(DayPace::minutes(0))->toBe(0);
});

it('reads a unit over all its cards: a second failure returns it, all answered walks it — and a walked partner card walks no exchange', function () {
    $cards = [
        windowCard(Stage::Words, CardKind::WordIntro, UnitKind::Word, 'v1', answered: true),
        windowCard(Stage::Words, CardKind::WordChoose, UnitKind::Word, 'v1', answered: true),
        windowCard(Stage::Words, CardKind::WordIntro, UnitKind::Word, 'v2', answered: true),
        windowCard(Stage::Words, CardKind::WordChoose, UnitKind::Word, 'v2', returns: true),
        windowCard(Stage::Words, CardKind::WordIntro, UnitKind::Word, 'v3', answered: true),
        windowCard(Stage::Words, CardKind::WordChoose, UnitKind::Word, 'v3'),
        windowCard(Stage::Dialogue, CardKind::DialoguePartner, UnitKind::Exchange, 'x1', answered: true),
        windowCard(Stage::Speak, CardKind::SpeakAnswer, UnitKind::Exchange, 'x1'),
    ];
    $states = UnitStates::of($cards);

    expect($states[UnitStates::key('S1', UnitKind::Word, 'v1')])->toBe(UnitState::Done)
        ->and($states[UnitStates::key('S1', UnitKind::Word, 'v2')])->toBe(UnitState::ReturnsTomorrow)
        ->and($states[UnitStates::key('S1', UnitKind::Word, 'v3')])->toBe(UnitState::Pending)
        ->and($states[UnitStates::key('S1', UnitKind::Exchange, 'x1')])->toBe(UnitState::Pending)
        // The tab's brow (наряд FIX-3 §9): all units, walked, and how many came back from earlier days — none here.
        ->and(ProgramSummary::of(array_values($states), 0))->toEqual(new ProgramSummary(4, 1, 0));
});

it('paints a slot with the first tone it knows, and with the theme’s empty slot when it knows none', function () {
    expect(ImageTones::first(null, '#978e82', '#111111'))->toBe('#978E82')
        ->and(ImageTones::first('not a tone', null))->toBe(ImageTones::THEME)
        ->and(ImageTones::first())->toBe('#E3DCCF');
});

// Canon (DAY-UI-3): «промпт фото не по голому слову». Catches the rung «the word alone» — asked
// «marketing», the vendor answered with a supermarket (phone, 14.09).
it('asks a word’s photo by its description, then the word WITH the scene’s theme, then the theme on a page of its own — never by the bare word', function () {
    $scene = PlanScene::fromBrief(PlanSceneId::generate(), PlanId::generate(), new SceneBrief(
        1, SceneKind::Situation, 1, 'Приём у врача', 'At the doctor’s', 'описать боль', ['описать боль'],
        'Patient', 'Пациент', 'Doctor', 'Врач', 'Situation: …', 'realistic photo of a consultation in a doctor’s office, patient seated',
    ));
    $term = static fn (?string $prompt, string $text, int $position = 0): PlanTerm => PlanTerm::reconstitute(
        PlanTermId::generate(), $scene->id(), TermKind::Word, 'v1', $position, $text, 'перевод', null, null, null, null, null, [], $prompt, null,
    );
    $titles = new PlanTitles('Врач', 'Doctor', 'Приём', 'До приёма', 'Приём был', 'clinic corridor', 'Patient', 'Пациент');
    $asked = static fn (PlanTerm $t): array => array_map(
        static fn (ImageQuery $q): string => $q->page === 1 ? $q->text : "{$q->text} #{$q->page}",
        ImageQueries::forTerm($t, $scene),
    );

    expect($asked($term('a thermometer', 'fever')))->toBe(['a thermometer', 'fever, doctor’s office', 'doctor’s office #2'])
        ->and($asked($term(null, 'marketing', 3)))->toBe(['marketing, doctor’s office', 'doctor’s office #5'])
        ->and(array_map(static fn (ImageQuery $q): string => $q->text, ImageQueries::forTerm($term(null, 'marketing'), $scene)))->not->toContain('marketing')
        ->and(array_map(static fn (ImageQuery $q): string => $q->text, ImageQueries::forScene($scene, $titles)))
        ->toBe(['realistic photo of a consultation in a doctor’s office, patient seated', 'At the doctor’s', 'clinic corridor']);
});

it('names a scene’s theme by the place its photo description names, else by its title', function () {
    $scene = static fn (string $prompt): PlanScene => PlanScene::fromBrief(PlanSceneId::generate(), PlanId::generate(), new SceneBrief(
        1, SceneKind::Situation, 1, 'Условия', 'Job Terms', 'обсудить', ['обсудить'], 'Candidate', 'Кандидат', 'Recruiter', 'Рекрутер', 'Situation: …', $prompt,
    ));

    expect(ImageQueries::theme($scene('realistic photo of a doctor speaking with a patient in a simple clinic office')))->toBe('simple clinic office')
        ->and(ImageQueries::theme($scene('a business interview discussion in a meeting room, two people')))->toBe('meeting room')
        ->and(ImageQueries::theme($scene('a small clinic reception desk with a patient checking in')))->toBe('Job Terms')
        ->and(ImageQueries::theme($scene('a person discussing job terms on a laptop video call')))->toBe('Job Terms');
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
