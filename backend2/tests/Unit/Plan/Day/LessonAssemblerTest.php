<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Lesson\Filler;
use App\Modules\Plan\Domain\Lesson\LessonAssembler;

/*
 * THE LESSON PUT TOGETHER FROM ITS TWO STAGES (наряд GEN-4, 3.8): «в прежний контракт клиента, поле в поле … in_dialogue
 * выставляет код по строкам ученика … must_say, must_understand, partner_line, pairs_with — внутренние … строкам без конечного
 * знака код добавляет «.», вопросы уже с «?»; чтения без знаков». The canon day, assembled.
 */

it('assembles the lesson in the shape a scene has always stored, and nothing of the stages\' own', function () {
    $lesson = LessonAssembler::assemble(dayCanonSkeleton(), dayCanonDialogue())->toArray();
    $json = (string) json_encode($lesson);

    expect(array_keys($lesson))->toBe(['topic', 'learner_role', 'role_gender', 'dialogue', 'phrases', 'listening', 'vocabulary'])
        ->and($lesson['topic']['title_target'])->toBe('Experiență de muncă')
        ->and($lesson['learner_role'])->toBe(['role_target' => 'Candidat', 'role_native' => 'Кандидат'])
        ->and($lesson['role_gender'])->toBe('male')
        ->and(count($lesson['dialogue']))->toBe(8)
        ->and(count($lesson['listening']['questions']))->toBe(3)
        ->and($json)->not->toContain('must_say')->not->toContain('must_understand')->not->toContain('partner_line')->not->toContain('pairs_with');
});

// Catches a frame or a line dealt without its full stop — the skeleton writes none — a question given a «.» after its «?»,
// and a reading, a filler, a check or the listening «closed» as if it were a sentence.
it('closes a frame and every line that has no mark of its own with «.», and leaves the rest as written', function () {
    $lesson = LessonAssembler::assemble(dayCanonSkeleton(), dayCanonDialogue());
    $p1 = $lesson->phrase('p1');
    $x1 = $lesson->exchange(1);
    $x2 = $lesson->exchange(2);

    expect([$p1?->frameTarget, $p1?->frameNative])->toBe(['Mă numesc ___.', 'Меня зовут ___.'])
        ->and($lesson->phrase('p6')?->frameTarget)->toBe('Postul include ___?')
        ->and($p1?->pronunciationNative)->toBe('мэ нумеск ___')
        ->and(array_map(static fn (Filler $f): string => $f->target, $p1?->fillers() ?? []))->toBe(['Andrei', 'Mihai'])
        ->and([$x1?->learner()?->textTarget, $x1?->learner()?->textNative])->toBe(['Mă numesc Andrei.', 'Меня зовут Андрей.'])
        ->and($x1?->partner()?->textTarget)->toBe('Cum vă numiți?')
        ->and($x2?->learner()?->simplifiedVariants)->toBe(['Vreau postul de vânzător.'])
        ->and($x2?->learner()?->pronunciationNative)->toBe('кандидез пентру постул де вынзэтор')
        ->and($x1?->check->options[0]->textTarget)->toBe('Numele candidatului')
        ->and($lesson->exchange(6)?->learner()?->textTarget)->toBe('Postul include lucrul cu clienții?');
});

// Catches marks that lie about what the dialogue says: the skeleton's marks kept, a filler said and marked false, one never
// said and marked true.
it('marks in_dialogue exactly the fillers the learner\'s lines say, whatever the skeleton marked', function () {
    $skeleton = dayCanonSkeleton(static function (array $raw): array {
        foreach ($raw['phrases'] as $i => $frame) {
            foreach ($frame['slot']['fillers'] ?? [] as $f => $filler) {
                $raw['phrases'][$i]['slot']['fillers'][$f]['in_dialogue'] = $f === 1;
            }
        }

        return $raw;
    });
    $lesson = LessonAssembler::assemble($skeleton, dayCanonDialogue());
    $marks = static fn (string $id): array => array_map(static fn (Filler $f): bool => $f->inDialogue, $lesson->phrase($id)?->fillers() ?? []);

    expect($marks('p1'))->toBe([true, false])
        ->and($marks('p3'))->toBe([true, false, false])
        ->and($marks('p6'))->toBe([true, false, false])
        ->and($lesson->phrase('p7')?->slot)->toBeNull();
});

// A word of the day is used where the day says it: a frame by its id, a partner line where the dialogue says it — the partner's
// message of the exchange that carries it. Catches a word card pointing at a line id the client never sees, and at a line no
// exchange says.
it('names a word\'s partner lines by the message that says them, and drops a line no exchange carries', function () {
    $words = static fn ($lesson): array => array_column(array_map(static fn ($v): array => ['id' => $v->id, 'used_in' => $v->usedIn], $lesson->vocabulary), 'used_in', 'id');
    $canon = $words(LessonAssembler::assemble(dayCanonSkeleton(), dayCanonDialogue()));
    $unsaid = $words(LessonAssembler::assemble(dayCanonSkeleton(), dayCanonDialogue(dcAt(8, static fn (array $e): array => [...$e, 'partner_line' => null]))));

    expect($canon['v1'])->toBe(['p2', 'A2'])
        ->and($canon['v3'])->toBe(['p3', 'p4', 'A3', 'A4', 'A6'])
        ->and($canon['v9'])->toBe(['A6', 'A8'])
        ->and($unsaid['v7'])->toBe(['p7']);
});
