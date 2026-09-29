<?php

declare(strict_types=1);
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Infrastructure\Prompt\PlanPromptFiles;

/*
 * THE CANON DAY IS CLEAN (наряд GEN-4): the skeleton of the architect's own test input and a dialogue written to it break no
 * rule of either stage's check — the baseline every rule's test breaks once. The skeleton is the one the dialogue prompt's
 * TEST INPUT carries, with the one warning of that example taken out.
 */

it('finds nothing in the canon skeleton', function () {
    expect(skeletonFound(dayCanonSkeleton()))->toBe([]);
});

it('finds nothing in the canon dialogue', function () {
    expect(dialogueFound(dayCanonDialogue()))->toBe([]);
});

it('counts the canon dialogue as the architect did: seven partner lines, no unpaired frame, a rescue', function () {
    expect(dayCanonSkeleton()->dialogueCount())->toBe(8);
});

// The example of `lesson_dialogue.v1.1` as written, byte for byte from the file: one warning — «post» (v2) names a6 in its
// `used_in`, and a6 does not say it — and nothing fatal. Catches a rule that reads the architect's own example otherwise.
it('finds in the dialogue prompt\'s own example skeleton the one warning the canon takes out', function () {
    $raw = (string) file_get_contents(PlanPromptFiles::path('dialogue'));
    $input = substr($raw, (int) strpos($raw, "\n---\n\nTEST INPUT\n\n"));
    $json = substr($input, (int) strpos($input, "SKELETON:\n") + strlen("SKELETON:\n"));
    $end = strpos($json, "\n---\n");
    $example = (new LessonParser)->skeleton(json_decode($end === false ? $json : substr($json, 0, $end), true, flags: JSON_THROW_ON_ERROR));

    expect(skeletonFound($example))->toBe(['vocab.used_in_wrong@v2']);
});
