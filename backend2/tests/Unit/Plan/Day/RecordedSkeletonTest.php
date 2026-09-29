<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Blueprint\SurvivalSet;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonCheck;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/*
 * THE TAIL OF THE DAY ON THE ANSWERS THE MODELS GAVE (наряд GEN-4c §5 — tests on the canon, not on the code): skeletons GEN-4
 * and GEN-4b recorded, copied into `tests/Fixtures/plan-day/recorded/` with the context of their day — the scene's survival
 * set, the pair, the learner's gender and own words (the plan's goal) — read by the skeleton's check as the build reads them.
 */

/** @return array<string, mixed> a recorded skeleton with its day's context */
function rsRecorded(string $name): array
{
    return json_decode((string) file_get_contents(__DIR__."/../../../Fixtures/plan-day/recorded/{$name}.json"), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * What the skeleton's check finds in a recorded skeleton, as `code@address` — its learner's own words as the plan had them,
 * or the ones given.
 *
 * @return list<string>
 */
function rsFound(string $name, ?string $learnerWords = null): array
{
    $r = rsRecorded($name);
    $context = new SkeletonContext(
        SurvivalSet::fromModel($r['survival']['must_say'], $r['survival']['must_understand']),
        8,
        12,
        lessonPacks()->for($r['native']),
        lessonPacks()->for($r['target']),
        $r['gender'] === null ? null : VoiceGender::from($r['gender']),
        learnerWords: $learnerWords ?? $r['goal'],
    );

    return array_values(array_unique(array_map(
        static fn ($v): string => "{$v->code}@{$v->address}",
        (new SkeletonCheck)->run((new LessonParser)->forNative($r['native'])->skeleton($r['skeleton']), $context),
    )));
}

// Наряд GEN-4c §1–§2: the e2e of GEN-4b (ru→ro, «Собеседование в пятницу, боюсь вопросов про опыт» — no detail of the
// learner's) taught «depozit», said only in the placeholder «Am lucrat la un depozit», and answered «Postul include ___?»
// with «Postul include lucru cu clienții și pregătirea documentelor.» — no «Da.», no «Nu.». Catches the two holes let through
// again, and «pregătirea actelor», the second word the same skeleton took from a placeholder of p6.
it('finds in the e2e day of GEN-4b the word of a placeholder and the yes-or-no reply with no «Da» or «Nu»', function () {
    expect(rsFound('gen4b-e2e-b'))->toContain('vocab.from_placeholder@v3')
        ->toContain('vocab.from_placeholder@v7')
        ->toContain('partner.yes_no_missing@a6')
        // «Care este programul?» asks for a fact, and its reply is the fact.
        ->not->toContain('partner.yes_no_extra@a7')
        ->not->toContain('partner.yes_no_missing@a7')
        // «lucru cu clienții» is said by a6: no word of a placeholder alone.
        ->not->toContain('vocab.from_placeholder@v6');
});

// Наряд GEN-4c §2: «ответ на вопрос с вопросительным словом начинается с да/нет-слова → yes_no_extra». The e2e of GEN-4 (§6)
// answered «Care sunt sarcinile principale?» and «Care este programul de lucru?» with «Da. …»; the Luna day 14 answered «Care
// sunt atribuțiile pentru ___?» with «Da, postul include…». Catches a reply that opens with a «Da» the question never asked for.
it('finds a «Da» opening the reply to a question that asks for a fact', function (string $name, array $at) {
    $found = rsFound($name);
    foreach ($at as $line) {
        expect($found)->toContain("partner.yes_no_extra@{$line}");
    }
    expect(array_filter($found, static fn (string $f): bool => str_starts_with($f, 'partner.yes_no_missing')))->toBe([]);
})->with([
    'the e2e of GEN-4 — «Da. Programul este…»' => ['gen4-e2e', ['a5', 'a6']],
    'the Luna day 14 — «Da, postul include…»' => ['gen4-luna-14', ['a5']],
]);

// Наряд GEN-4c §1: «детали ученика — то, что скелет получает на входе как данные самого ученика; слово из детали — не находка».
// gpt-5.4 on the prompts v1.1 (day 05, es→en, «vuelo a Londres, primera vez, miedo al control de pasaportes») taught «passport»
// of the filler «passport» / «pasaporte» — the learner's own «pasaportes», by form. Catches a word of the learner's detail
// refused as a placeholder's, and a placeholder word («tourism», the purpose the skeleton chose) let through beside it.
it('lets a word of the learner\'s own detail be, and finds the placeholder word beside it', function () {
    expect(rsFound('gen4b-gpt54-05'))->not->toContain('vocab.from_placeholder@v1')
        ->toContain('vocab.from_placeholder@v6')
        // The same skeleton for a learner who gave no details: the passport is a placeholder's too.
        ->and(rsFound('gen4b-gpt54-05', ''))->toContain('vocab.from_placeholder@v1');
});
