<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonSeamVerdict;
use App\Modules\Plan\Application\Dto\NativeSeamJudgeRequest;
use App\Modules\Plan\Application\Service\LessonSeamJudge;
use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Lesson\AskReplies;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use App\Modules\Plan\Infrastructure\Prompt\PlanPromptFiles;
use App\Modules\Plan\Infrastructure\Prompt\PlanSchemas;

/*
 * THE SEAM JUDGE'S SECOND QUESTION, IN THE SAME CALL (наряд GEN-4c §3, `lesson_seam_judge.v1.2`): for every reply of the partner
 * to a question of the learner's, does it name a filler of the question — word for word, in another form or by its meaning. The
 * answer is strict JSON: the seams' verdicts as they were, and the ids of the replies that name one. Contract tests of the
 * answer's reading — the model is the fake, answering what each test hands it.
 */

/** The reply of the e2e of GEN-4b (a6) — «Postul include ___?» answered with two of its fillers in other words. */
function sjrReplies(): array
{
    return [[
        'id' => 'a6',
        'question' => 'Postul include ___?',
        'values' => ['lucrul cu marfa', 'lucrul cu clienții', 'pregătirea actelor'],
        'reply' => 'Postul include lucru cu clienții și pregătirea documentelor.',
    ]];
}

/** @param array<string, mixed>|Closure(NativeSeamJudgeRequest): array<string, mixed> $answer */
function sjrJudge(array|Closure $answer): array
{
    $fake = new FakePlanModel(judge: static fn (NativeSeamJudgeRequest $request): array => $answer instanceof Closure ? $answer($request) : $answer);
    $verdict = (new LessonSeamJudge($fake))->judge(dayCanonSkeleton()->phrases(), 'Russian', sjrReplies(), 'Romanian');

    return [$verdict, $fake];
}

// Наряд GEN-4c §3: «ответ — строгий JSON, прежние вердикты швов без изменений; к нему добавляется список id реплик с
// находкой»; «partner.names_filler_meaning — карточка partner_line, причина …». Catches the second list read as a seam, a
// named reply found at no partner line or with another reason, and the seams' own verdicts lost beside it.
it('reads the seams as before and every reply the judge names as partner.names_filler_meaning at its line', function () {
    [$verdict, $fake] = sjrJudge(static fn (NativeSeamJudgeRequest $r): array => [
        'verdicts' => array_map(static fn (string $id): array => ['id' => $id, 'reads' => $id !== 'p3.f2'], $r->ids()),
        'replies_naming_values' => ['a6'],
    ]);

    expect($verdict->status)->toBe(LessonSeamVerdict::JUDGED)
        ->and($verdict->replies)->toBe(1)
        ->and($verdict->naming)->toBe(['a6'])
        ->and(array_map(static fn ($v): string => "{$v->code}@{$v->address}", $verdict->violations))
        ->toBe(['filler.native_seam@p3.f2', 'partner.names_filler_meaning@a6'])
        ->and($verdict->violations[1]->detail)->toStartWith('the reply names or paraphrases a filler of the frame: one general fact about the matter of the scene instead, true whatever was asked (')
        ->and($verdict->violations[1]->detail)->toContain('«pregătirea actelor»')
        // One call for both questions: the sentences and the replies travel together.
        ->and($fake->judgeCalls)->toBe(1)
        ->and($fake->judgeRequests[0]->replyIds())->toBe(['a6'])
        ->and($fake->judgeRequests[0]->targetLanguage)->toBe('Romanian');
});

// Canon (the judge is a warning, never fatal): an id nobody sent, an id twice, a list missing — none is a finding, and the
// seams read the same. Catches a line found that the day does not have, a reply found twice, and a call refused whole over a
// list it only half answered.
it('finds no reply the judge was not asked about, each once, and keeps the seams when the list is missing', function (array $answer, array $found, string $note) {
    [$verdict] = sjrJudge(static fn (NativeSeamJudgeRequest $r): array => [
        'verdicts' => array_map(static fn (string $id): array => ['id' => $id, 'reads' => true], $r->ids()),
        ...$answer,
    ]);

    expect($verdict->status)->toBe(LessonSeamVerdict::JUDGED)
        ->and(array_map(static fn ($v): string => "{$v->code}@{$v->address}", $verdict->violations))->toBe($found)
        ->and($verdict->note)->toBe($note);
})->with([
    'none named' => [['replies_naming_values' => []], [], ''],
    'an id not sent' => [['replies_naming_values' => ['a9', 'p3']], [], ''],
    'an id twice' => [['replies_naming_values' => ['a6', 'a6']], ['partner.names_filler_meaning@a6'], ''],
    'no list at all' => [[], [], 'no replies_naming_values in the answer'],
]);

// Наряд GEN-4c §3: «второй вопрос в ТОМ ЖЕ вызове, без нового вызова». Catches a day with replies and no native seam left
// unjudged, and a reply list read with no list in the answer.
it('reads the replies of a day with no native seam, and calls it unavailable without its list', function () {
    $fake = new FakePlanModel(judge: static fn (): array => ['verdicts' => [], 'replies_naming_values' => ['a6']]);
    $named = (new LessonSeamJudge($fake))->judge([], 'Russian', sjrReplies(), 'Romanian');
    $silent = (new LessonSeamJudge(new FakePlanModel(judge: static fn (): array => ['verdicts' => []])))->judge([], 'Russian', sjrReplies(), 'Romanian');

    expect([$named->status, $named->naming])->toBe([LessonSeamVerdict::JUDGED, ['a6']])
        ->and($silent->status)->toBe(LessonSeamVerdict::UNAVAILABLE)
        ->and((new LessonSeamJudge(new FakePlanModel))->judge([], 'Russian', [], 'Romanian')->status)->toBe(LessonSeamVerdict::NOTHING);
});

// Наряд GEN-4c §3: «для каждой пары ask-каркас → реплика-ответ A». Catches a question of the partner sent as a reply, a reply
// to an answer frame sent, a question with no fillers sent (nothing to name), and a line sent twice.
it('sends every statement paired with an ask frame that has fillers, once', function () {
    expect(AskReplies::of(dayCanonSkeleton()))->toBe([[
        'id' => 'a6',
        'question' => 'Postul include ___?',
        'values' => ['lucrul cu clienții', 'aranjarea mărfii', 'comenzi online'],
        'reply' => 'Da. Și lucrați în ture, dimineața sau seara.',
    ]])
        // p7 «Care este programul obișnuit?» has no slot: its reply a7 names no filler of it.
        ->and(AskReplies::of(dayCanonSkeleton(), ['a7']))->toBe([]);
});

// The prompt's INPUTS and OUTPUT (`lesson_seam_judge.v1.2`): the replies after the sentences, the target's language named,
// `none` for an empty list; the schema — the sentences' verdicts as they were, the replies' ids one of those sent. Catches a
// reply the model cannot name back, and a request that drops the sentences a day of replies alone does not have.
it('asks the second question in the same message and the same schema', function () {
    $request = new NativeSeamJudgeRequest('Russian', [], 'Romanian', sjrReplies());
    $user = (new PlanPromptFiles)->judgeUser($request);
    $schema = PlanSchemas::seamJudge($request->ids(), $request->replyIds());

    expect($user)->toContain("ITEMS (id · the pattern with its slot · the value put into the slot · the sentence they make):\nnone\n")
        ->and($user)->toContain("TARGET_LANGUAGE: Romanian\n")
        ->and($user)->toContain('"id":"a6","question":"Postul include ___?"')
        ->and($schema['required'])->toBe(['verdicts', 'replies_naming_values'])
        ->and($schema['properties']['replies_naming_values']['items']['enum'])->toBe(['a6'])
        ->and((new PlanPromptFiles)->judgeVersion())->toBe('lesson_seam_judge.v1.2')
        ->and(LessonCodes::JUDGED)->toContain(LessonCodes::NAMES_FILLER_MEANING)
        // Ranked for the repairs after the yes or no — and never fatal: the judge reads a skeleton already taken.
        ->and(LessonCodes::repairRank(LessonCodes::NAMES_FILLER_MEANING))->toBe(3)
        ->and(LessonCodes::BUDGETED)->not->toContain(LessonCodes::NAMES_FILLER_MEANING);
});
