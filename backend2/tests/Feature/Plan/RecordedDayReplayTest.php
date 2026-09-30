<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\DialogueRequest;
use App\Modules\Plan\Application\Dto\LessonCardRepairRequest;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\NativeSeamJudgeRequest;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Application\Service\LessonBuildService;
use App\Modules\Plan\Application\Service\LessonRequests;
use App\Modules\Plan\Domain\Blueprint\SurvivalSet;
use App\Modules\Plan\Domain\Lesson\EarlierDay;
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
 * runs it over them. And (наряд GEN-4c-2) day 2 of the ru→en plan, built on closing its day 1 as production builds it:
 * `gen4c2-e2e-en-day2.json` — the request with the story so far, both answers of each stage, every repair, and the seam
 * judge's answers of v1.3 over that day (`tools/gen4c-replay.php`, a live judge).
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

/** A request as a recorded fixture keeps it — the story so far with it. */
function rdrRequestOf(array $r): LessonRequest
{
    return new LessonRequest(
        topic: $r['topic'],
        topicDescription: $r['topic_description'],
        survival: SurvivalSet::fromColumns($r['must_say'], $r['must_understand']),
        targetLanguage: $r['target_language'],
        nativeLanguage: $r['native_language'],
        level: PlanLevel::from($r['level']),
        learnerGender: VoiceGender::from($r['learner_gender']),
        vocabularyMin: $r['vocabulary'][0],
        vocabularyMax: $r['vocabulary'][1],
        roles: new LessonRoles(...$r['roles']),
        earlierDays: new EarlierDays(array_map(
            static fn (array $d): EarlierDay => new EarlierDay($d['number'], $d['title_target'], $d['partner_role_target'], VoiceGender::from($d['partner_gender']), $d['lines'], $d['frames'], $d['words']),
            $r['earlier_days'],
        )),
        targetLangCode: $r['target'],
        nativeLangCode: $r['native'],
        sceneId: $r['scene_id'],
    );
}

// Наряд GEN-4c-2 §2: «после починок ни одна реплика не называет наполнение (код и судья чисты)». On day 2 of the e2e ru→en the
// judge v1.2, asked for the LIST of the replies that name a value, listed the repaired a8 «Yes. New staff receive training
// during the first week.» again, and the finding stayed on a line that names none: a list is what it gives a reply sent alone
// whatever it says (20 of 20 on day 2 ru→ro). v1.3 answers a verdict for every reply. Catches a reply's own verdict not read,
// a yes-or-no reply repaired without its «Yes.», and a finding of the judge left after the judge said the repair helped.
it('reads the repaired replies again by their own verdicts: a7 and a8 of day 2 ru→en helped, no reply left naming a value', function () {
    $f = json_decode((string) file_get_contents(__DIR__.'/../../Fixtures/plan-day/recorded/gen4c2-e2e-en-day2.json'), true, flags: JSON_THROW_ON_ERROR);
    $recorded = [];
    foreach ($f['repairs'] as $one) {
        $recorded[$one['address']][] = $one['answer'];
    }
    $fake = new FakePlanModel(
        skeleton: static fn (LessonRequest $r, int $call): array => $f['skeleton'][$call - 1],
        dialogue: static fn (DialogueRequest $r, int $call): array => $f['dialogue'][$call - 1],
        repair: static function (LessonCardRepairRequest $r) use (&$recorded): array {
            return ($recorded[$r->address] ?? []) !== [] ? array_shift($recorded[$r->address]) : ['card' => $r->card];
        },
        judge: static fn (NativeSeamJudgeRequest $r, int $call): array => $f['judge'][$call - 1]['answer'],
    );
    app()->instance(PlanModelPort::class, $fake);
    app()->forgetInstance(LessonBuildService::class);

    $outcome = app(LessonBuildService::class)->build(rdrRequestOf($f['request']));
    $repairs = array_column(array_filter($outcome->log->repairs, static fn (array $r): bool => $r['stage'] === 'skeleton'), null, 'address');
    $codes = array_column($outcome->findings, 'code');

    expect($outcome->lesson)->not->toBeNull()
        // The first read names both (a7 «The pay is per month.», a8 «You can start next week if we choose you.»).
        ->and($repairs['a7']['sent_for'])->toContain('partner.names_filler_meaning')
        ->and($repairs['a8']['sent_for'])->toContain('partner.names_filler_meaning')
        ->and([$repairs['a7']['kept'], $repairs['a7']['helped']])->toBe([true, true])
        ->and([$repairs['a8']['kept'], $repairs['a8']['helped']])->toBe([true, true])
        ->and($outcome->skeleton?->partnerLine('a8')?->textTarget)->toBe('Yes. New staff receive training during the first week.')
        ->and($fake->judgeRequests[1]->replyIds())->toBe(['a7', 'a8'])
        ->and($codes)->not->toContain('partner.names_filler_meaning')
        ->and($codes)->not->toContain('partner.yes_no_missing')
        ->and($codes)->not->toContain('vocab.from_placeholder');
});
