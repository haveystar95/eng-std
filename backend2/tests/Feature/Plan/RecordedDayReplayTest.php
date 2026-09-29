<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonCardRepairRequest;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Application\Service\LessonBuildService;
use App\Modules\Plan\Application\Service\LessonRequests;
use App\Modules\Plan\Domain\Blueprint\SurvivalSet;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\Lesson\LessonRoles;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * A DAY OF THE E2E OF GEN-4c, BUILT AGAIN FROM ITS OWN ANSWERS (наряд GEN-4c §5 — tests on the canon, not on the code): the
 * skeleton, the dialogue and every repair the models wrote for day 1 of the ru→ro plan (run 3), read off the e2e journal
 * into `tests/Fixtures/plan-day/recorded/gen4c-e2e-ro-run3.json`, answered again by the fake — the conveyor as production
 * runs it over them.
 */

/** @return array<string, mixed> */
function rdrRecorded(): array
{
    return json_decode((string) file_get_contents(__DIR__.'/../../Fixtures/plan-day/recorded/gen4c-e2e-ro-run3.json'), true, flags: JSON_THROW_ON_ERROR);
}

function rdrRequest(array $r): LessonRequest
{
    return new LessonRequest(
        topic: $r['scene']['title_native'],
        topicDescription: LessonRequests::topicDescription($r['scene']['topic_description'], $r['goal']),
        survival: SurvivalSet::fromColumns($r['scene']['must_say'], $r['scene']['must_understand']),
        targetLanguage: 'Romanian',
        nativeLanguage: 'Russian',
        level: PlanLevel::Beginner,
        learnerGender: VoiceGender::Male,
        vocabularyMin: 8,
        vocabularyMax: 12,
        roles: new LessonRoles('Candidat', 'Кандидат', 'Recepționer', 'Администратор'),
        earlierDays: new EarlierDays,
        targetLangCode: 'ro',
        nativeLangCode: 'ru',
        sceneId: $r['scene']['id'],
    );
}

// Наряд GEN-4c §6: «ни одного слова словаря из заглушки». The e2e sent «a veni», a stop word, to a repair, and the repair
// wrote «casier» — the value of p2 the skeleton chose for a learner who named no position; kept, it left the day a word of a
// placeholder. Catches a repair kept that brings a finding the repairs take first to its own card — and the other repair of
// the stage (p6, a letter of another writing) refused with it.
it('keeps no repair that brings its card a finding the repairs take first: «casier» for «a veni»', function () {
    $r = rdrRecorded();
    $fake = new FakePlanModel(
        skeleton: static fn (): array => $r['skeleton'],
        dialogue: static fn (): array => $r['dialogue'],
        repair: static fn (LessonCardRepairRequest $request): array => ['card' => $r['repairs'][$request->address] ?? $request->card],
    );
    app()->instance(PlanModelPort::class, $fake);
    app()->forgetInstance(LessonBuildService::class);

    $outcome = app(LessonBuildService::class)->build(rdrRequest($r));
    $repairs = array_column($outcome->log->repairs, null, 'address');

    expect($outcome->lesson)->not->toBeNull()
        ->and($repairs['v3']['kept'])->toBeFalse()
        ->and($repairs['v3']['broke'])->toBe(['vocab.from_placeholder'])
        ->and($repairs['v3']['note'])->toBe('the repair brings a finding the repairs take first')
        ->and($outcome->skeleton?->vocabularyItem('v3')?->termTarget)->toBe('a veni')
        ->and($repairs['p6']['kept'])->toBeTrue()
        ->and($outcome->skeleton?->frame('p6')?->phrase->pronunciationNative)->toBe('да, сунт гата')
        ->and(array_column($outcome->findings, 'code'))->not->toContain('vocab.from_placeholder');
});
