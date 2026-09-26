<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Service\ConversationMaterial;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Lesson\LessonAssembly;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\PlanTermId;
use App\Modules\Plan\Domain\ValueObject\TermKind;

/**
 * THE TALK'S HINT IS THE LESSON'S OWN LINE (наряд LANG-1b §3), canon on the live day 1 of LANG-1 part D, ru→de (plan
 * `01M3DGEQ5JN32N97SH0PQ66DEN`, the lesson as the e2e stand holds it — `docs/research/lang-1b/fixtures/ru-de-day1.lesson.json`).
 * The learner got «Мне нужно запись на приём» as the hint of p1 — the native frame «Мне нужно ___.» with its value — where the
 * lesson's line says «Мне нужна запись на приём.»; and «Мне подходит десять часов.» for the lesson's «Десять часов мне
 * подходит.». Read by the same steps the server takes: the answer served, its terms written, the talk's material read.
 */

/** @return array<string, PlanTerm> the phrases of the ru→de day, by ref */
function thlPhrases(?callable $edit = null): array
{
    $answer = json_decode((string) file_get_contents(__DIR__.'/../../../docs/research/lang-1b/fixtures/ru-de-day1.lesson.json'), true);
    if ($edit !== null) {
        $answer = $edit($answer);
    }
    $scene = PlanSceneId::fromString('01M3DGF01S1YRDQGA7NB4HWBS9');
    $served = LessonAssembly::serve((new LessonParser)->parse($answer), $scene->value, lessonPacks()->for('de'));
    $out = [];
    foreach (PlanTerm::fromLesson($scene, $served, static fn (): PlanTermId => PlanTermId::generate(), lessonPacks()->for('de')->sentenceEnds(), lessonPacks()->for('ru')->sentenceEnds()) as $term) {
        if ($term->kind() === TermKind::Phrase) {
            $out[$term->ref()] = $term;
        }
    }

    return $out;
}

// CATCHES the hint put together from the frame and its value again — the grammar of the native frame broken on the value
// («нужно запись»), a word order the lesson did not say.
it('gives the talk the lesson\'s own line of each construction, not its frame put together with the value', function () {
    $phrases = thlPhrases();

    expect($phrases['p1']->textNative())->toBe('Мне нужно запись на приём.')
        ->and(ConversationMaterial::lessonLine($phrases['p1']))->toBe('Мне нужна запись на приём.')
        ->and($phrases['p5']->textNative())->toBe('Мне подходит десять часов.')
        ->and(ConversationMaterial::lessonLine($phrases['p5']))->toBe('Десять часов мне подходит.')
        ->and(ConversationMaterial::lessonLine($phrases['p2']))->toBe('У меня болит горло.')
        ->and(ConversationMaterial::lessonLine($phrases['p7']))->toBe('Хорошо, завтра в десять.');
});

// «Где строки нет — склейка как запас». CATCHES a construction no line of the lesson says left with no hint at all.
it('falls back to the frame put together with its value where no line of the lesson says the construction', function () {
    $phrases = thlPhrases(static function (array $answer): array {
        foreach ($answer['dialogue'] as $x => $exchange) {
            foreach ($exchange['messages'] as $m => $message) {
                if (($message['phrase_id'] ?? null) === 'p1') {
                    $answer['dialogue'][$x]['messages'][$m]['phrase_id'] = null;
                }
            }
        }

        return $answer;
    });

    expect($phrases['p1']->exampleNative())->toBeNull()
        ->and(ConversationMaterial::lessonLine($phrases['p1']))->toBe($phrases['p1']->textNative());
});
