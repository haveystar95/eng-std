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
 * runs it over them. And (наряды GEN-4c-2, GEN-4c-3) day 2 of the ru→en plan, built on closing its day 1 as production builds it, as
 * the code of GEN-4c-3 builds it again: `gen4c2-e2e-en-day2.json` — the request with the story so far and the answers that
 * build used (`tools/gen4c-replay.php`): both skeletons and the repairs as the day recorded them, the first dialogue as
 * recorded and the second asked live, the seam judge's first read of v1.3 and its second asked live.
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

// Наряд GEN-4c-3: «повтор дня 2 ru→en из записанных ответов итоговым кодом: починка p2 «I worked ___» теперь должна быть
// оставлена, шов «Я работал на гриле» чист … в уроке нет ни одной строки с двойным предлогом, p2 на месте». The day built
// again from its own answers — both skeletons, the repairs, the first dialogue — by the code of GEN-4c-3; what the day could
// not answer was asked once, live, and is kept in the fixture: the judge's second read (p2's seams now, a7 and a8) and the
// dialogue the first one could not be (its B2 said the old filler «the grill», `line.foreign_filler`). CATCHES the repair of
// p2 refused again for the native frame «Я работал ___» day 1 had, the day served «Я работал на на гриле.», and a reply read
// by another's verdict — the one read says a7 names nothing and a8 still names (1 of 19 reads of a8, GEN-4c-2 and GEN-4c-3):
// a7 helped, a8 not (GEN-4c-2).
it('keeps the repair of p2 whose native frame day 1 had: day 2 ru→en without «на на», each reply by its own verdict', function () {
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
    $lesson = $outcome->lesson?->toArray() ?? [];
    $doubled = [];
    array_walk_recursive($lesson, static function (mixed $text) use (&$doubled): void {
        if (is_string($text) && preg_match('/\b(\w+)\s+\1\b/iu', $text) === 1) {
            $doubled[] = $text;
        }
    });
    $saidOnP2 = [];
    foreach ($lesson['dialogue'] ?? [] as $exchange) {
        foreach ($exchange['messages'] as $message) {
            if (($message['phrase_id'] ?? null) === 'p2') {
                $saidOnP2[] = [$message['text_target'], $message['text_native']];
            }
        }
    }

    expect($outcome->lesson)->not->toBeNull()
        ->and([$repairs['p2']['kept'], $repairs['p2']['helped']])->toBe([true, true])
        ->and([$outcome->skeleton?->frame('p2')?->phrase->frameTarget, $outcome->skeleton?->frame('p2')?->phrase->frameNative])->toBe(['I worked ___', 'Я работал ___'])
        ->and($fake->judgeRequests[1]->ids())->toBe(['p2.f1', 'p2.f2', 'p2.f3'])
        ->and($saidOnP2)->toBe([['I worked on the grill.', 'Я работал на гриле.']])
        ->and($doubled)->toBe([])
        ->and($codes)->not->toContain('frame.known_repeat')
        ->and($codes)->not->toContain('filler.native_seam')
        ->and($codes)->not->toContain('filler.repeats_frame')
        // The first read names both (a7 «The pay is per month.», a8 «You can start next week if we choose you.»).
        ->and($repairs['a7']['sent_for'])->toContain('partner.names_filler_meaning')
        ->and($repairs['a8']['sent_for'])->toContain('partner.names_filler_meaning')
        ->and($fake->judgeRequests[1]->replyIds())->toBe(['a7', 'a8'])
        ->and([$repairs['a7']['kept'], $repairs['a7']['helped']])->toBe([true, true])
        ->and([$repairs['a8']['kept'], $repairs['a8']['helped']])->toBe([true, false])
        ->and($outcome->skeleton?->partnerLine('a8')?->textTarget)->toBe('Yes. New staff receive training during the first week.')
        ->and($codes)->not->toContain('partner.yes_no_missing')
        ->and($codes)->not->toContain('vocab.from_placeholder');
});
