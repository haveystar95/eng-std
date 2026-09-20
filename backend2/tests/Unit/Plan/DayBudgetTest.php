<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Assembly\DayAssembler;
use App\Modules\Plan\Domain\Assembly\PhraseCards;
use App\Modules\Plan\Domain\Assembly\PhrasesStage;
use App\Modules\Plan\Domain\Assembly\SceneMaterial;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\Lesson\LessonAssembly;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Service\ConversationRules;
use App\Modules\Plan\Domain\Service\DayBudget;
use App\Modules\Plan\Domain\Service\DayPace;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\DayType;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\PlanTermId;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\UnitKind;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;

/**
 * СКОЛЬКО ИДЁТ ДЕНЬ И ЧТО СТОИТ ПОД ПОТОЛКОМ (решение владельца 21.09, наряд CONV-1).
 *
 * Потолок в 32 минуты — про ПЯТЬ этапов карточек: он сторожит правило числа узнаваний, а чинится
 * раздачей. Разговор в него не входит — его минуты задаёт число ходов, — но в ДЛИТЕЛЬНОСТЬ дня,
 * которую видит ученик, входит.
 */
function dbDay(PlanLevel $level): array
{
    $sceneId = PlanSceneId::fromString('01J8DAYBADGET0000000000001');
    $packs = lessonPacks();
    $payload = FakePlanModel::lessonPayload(new LessonRequest(
        'Приём у врача', 'x', 'English', 'Russian', $level, null, 8, 8, FakePlanModel::roles(), new EarlierDays,
    ));
    $lesson = LessonAssembly::serve((new LessonParser)->parse($payload), $sceneId->value, $packs->for('en'));
    $terms = PlanTerm::fromLesson($sceneId, $lesson, static fn (): PlanTermId => PlanTermId::generate());
    $scene = new SceneMaterial($sceneId, $lesson, $terms, $packs->for('en'), $packs->for('ru'));

    $assembler = new DayAssembler(phrases: new PhrasesStage(new PhraseCards, new DayPace, PhrasesStage::BUDGET));

    return $assembler->sceneDay(
        PlanDayId::fromString('01J8DAYBADGET0000000000002'), $scene, [$sceneId->value => $scene], $level, [], [],
        static fn (): DayCardId => DayCardId::generate(),
    );
}

function dbBudget(): DayBudget
{
    return new DayBudget(new DayPace, new ConversationRules);
}

/**
 * Canon: «врач» с разговором собирается БЕЗ стоп-условия — потолок сторожит карточки, а шестой этап
 * в него не входит. Catches the ceiling read over the whole day: with the talk inside it, the clean
 * day would stop the build over a stage the owner ordered.
 */
it('keeps the clean doctor day under the ceiling — the talk is not counted into it', function (PlanLevel $level) {
    $cards = dbDay($level);
    $budget = dbBudget();

    $cardsMinutes = $budget->cardsMinutes($cards);
    $talkMinutes = $budget->talkMinutes(DayType::Scene, hasConversation: true);

    expect($budget->overCardsCeiling($cards))->toBeFalse()
        ->and($cardsMinutes)->toBeLessThanOrEqual($budget->ceilingMinutes())
        ->and($cardsMinutes)->toBe(30)
        ->and($talkMinutes)->toBe(3)
        // Длительность дня на экране — карточки ПЛЮС разговор, и она может быть больше потолка карточек.
        ->and($budget->dayMinutes($cards, DayType::Scene, hasConversation: true))->toBe(33)
        ->and($budget->dayMinutes($cards, DayType::Scene, hasConversation: false))->toBe(30);
})->with([PlanLevel::Beginner, PlanLevel::Intermediate]);

/**
 * ЧИСЛО ВЛАДЕЛЬЦА — «35 на враче» (вердикт 21.09). Столько идёт СЛЕДУЮЩИЙ день: он несёт назад
 * фразы, которых не услышал вчерашний разговор, — по одной карточке «Повтори свою реплику» на фразу
 * (наряд CONV-1, DECISIONS п. 362). На живом прогоне их было четыре из семи: 30 минут карточек + 2
 * минуты возвратов + 3 минуты разговора.
 *
 * Catches the returns being counted as free, and the ceiling being read over the day WITH the talk:
 * с разговором это 35 против 32, а карточки при этом стоят ровно на потолке.
 */
it('counts the day that carries yesterday\'s unsaid phrases back at 35 minutes, and still does not stop', function () {
    $budget = dbBudget();
    $cards = dbDay(PlanLevel::Beginner);
    $back = [];
    for ($i = 0; $i < 4; $i++) {
        $back[] = DayCard::dealt(
            DayCardId::generate(), PlanDayId::fromString('01J8DAYBADGET0000000000002'), Stage::Speak, 200 + $i,
            CardKind::SpeakRetell, ['scene_id' => '01J8DAYBADGET0000000000001'],
            App\Modules\Plan\Domain\ValueObject\CardSource::Returned, PlanDayId::fromString('01J8DAYBADGET0000000000002'),
            UnitKind::Exchange, 'x1',
        );
    }
    $day = [...$cards, ...$back];

    expect($budget->cardsMinutes($day))->toBe(32)
        ->and($budget->overCardsCeiling($day))->toBeFalse()
        ->and($budget->dayMinutes($day, DayType::Scene, hasConversation: true))->toBe(35);
});

/**
 * Canon: пять этапов карточек на 33 минуты — СТОП. Catches a ceiling that stopped being checked at
 * all once the talk moved out of it.
 */
it('stops on five card stages that cost more than the ceiling', function () {
    $budget = dbBudget();
    $cards = dbDay(PlanLevel::Beginner);

    // Ещё три минуты карточек — и день карточек перевалил за 32, с разговором или без него.
    $extra = [];
    for ($i = 0; $i < 6; $i++) {
        $extra[] = DayCard::dealt(
            DayCardId::generate(), PlanDayId::fromString('01J8DAYBADGET0000000000002'), Stage::Speak, 100 + $i,
            CardKind::SpeakAnswer, ['scene_id' => '01J8DAYBADGET0000000000001'],
            App\Modules\Plan\Domain\ValueObject\CardSource::Today, null, UnitKind::Exchange, 'x1',
        );
    }
    $heavy = [...$cards, ...$extra];

    expect($budget->cardsMinutes($heavy))->toBe(34)
        ->and($budget->overCardsCeiling($heavy))->toBeTrue()
        // И наоборот: 32 ровно — ещё не стоп.
        ->and($budget->overCardsCeiling(array_slice($heavy, 0, count($heavy) - 4)))->toBeFalse();
});

/**
 * Canon: у каждого вида разговора свой бюджет, и он не карточный. Catches minutes of the talk taken
 * from the day's pace table, where they have no card to hang on.
 */
it('gives every kind of talk its own minutes, and none to a day without one', function () {
    $budget = dbBudget();

    expect($budget->talkMinutes(DayType::Scene, true))->toBe(3)
        ->and($budget->talkMinutes(DayType::Rehearsal, true))->toBe(6)
        ->and($budget->talkMinutes(DayType::Review, true))->toBe(6)
        ->and($budget->talkMinutes(DayType::Scene, false))->toBe(0);
});
