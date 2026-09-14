<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Service\SpokenLines;
use App\Modules\Plan\Domain\Service\WordUsage;
use App\Modules\Plan\Domain\Service\Words;
use App\Modules\Plan\Domain\ValueObject\Speaker;
use App\Modules\Plan\Domain\ValueObject\VoiceCast;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/** EVERYTHING A DAY SAYS, WHOSE VOICE IT IS AND WHAT ITS FILE IS CALLED (DAY-UI-3). */

function spokenLesson(array $edit = []): App\Modules\Plan\Domain\Lesson\Lesson
{
    $payload = FakePlanModel::lessonPayload(new LessonRequest('Приём', 'Situation: …', 'English', 'Russian', PlanLevel::Beginner, 6, 8, 8, targetLangCode: 'en', nativeLangCode: 'ru'));

    return (new LessonParser())->parse(array_replace($payload, $edit));
}

it('names every line of the dialogue by its exchange and speaker, in the dialogue’s order', function () {
    $lines = SpokenLines::dialogue(spokenLesson());

    expect(array_column(array_slice($lines, 0, 4), 'ref'))->toBe(['x1', 'x1b', 'x2', 'x2b'])
        ->and($lines[0]['speaker'])->toBe(Speaker::Partner)
        ->and($lines[1]['speaker'])->toBe(Speaker::Learner)
        // Exchange 7 is opened by the learner: the learner's line comes first, and keeps its own name.
        ->and(array_column(array_slice($lines, 12, 2), 'ref'))->toBe(['x7b', 'x7'])
        ->and($lines)->toHaveCount(16);
});

it('gives the partner’s line the partner’s voice and everything else the learner’s', function () {
    expect(SpokenLines::speakerOf('x3'))->toBe(Speaker::Partner)
        ->and(SpokenLines::speakerOf('x3b'))->toBe(Speaker::Learner)
        ->and(SpokenLines::speakerOf('p2'))->toBe(Speaker::Learner)
        ->and(SpokenLines::speakerOf('v5'))->toBe(Speaker::Learner)
        ->and(VoiceCast::of(VoiceGender::Male)->learner())->toBe(VoiceGender::Female)
        ->and(VoiceCast::of(null)->partner)->toBe(VoiceGender::Female)
        ->and(VoiceCast::of(null)->genderOf(Speaker::Learner))->toBe(VoiceGender::Male);
});

// Owner, DAY-UI-3: «пол собеседника задаёт роль; если нет — собеседник женский»; «51 купленная фраза используется».
// Catches a cast that ignores the role, and phrases bought in one voice read later by the other.
it('casts a scene by its stored cast, then the role’s gender, then the voice its learner material was bought in, then the default', function () {
    $f = VoiceGender::Female;
    $m = VoiceGender::Male;

    expect(SpokenLines::castOf($m, $f, ['female' => 6], $f))->toBe($m)
        ->and(SpokenLines::castOf(null, $m, ['female' => 0, 'male' => 0], $f))->toBe($m)
        ->and(SpokenLines::castOf(null, null, ['female' => 6, 'male' => 0], $f))->toBe($m)
        ->and(SpokenLines::castOf(null, null, ['female' => 0, 'male' => 3], $f))->toBe($f)
        ->and(SpokenLines::castOf(null, null, ['female' => 2, 'male' => 3], $f))->toBe($f)
        ->and(SpokenLines::castOf(null, null, [], $f))->toBe($f);
});

it('reads the role’s gender a lesson names, and treats a lesson that names none (or an odd word) as not said', function () {
    expect(spokenLesson()->roleGender)->toBe(VoiceGender::Male)
        ->and(spokenLesson(['role_gender' => 'nobody'])->roleGender)->toBeNull()
        ->and(spokenLesson()->toArray()['role_gender'])->toBe('male');

    $payload = FakePlanModel::lessonPayload(new LessonRequest('Приём', 'Situation: …', 'English', 'Russian', PlanLevel::Beginner, 6, 8, 8, targetLangCode: 'en', nativeLangCode: 'ru'));
    unset($payload['role_gender']);
    expect((new LessonParser())->parse($payload)->roleGender)->toBeNull()
        ->and((new LessonParser())->parse($payload)->toArray())->not->toHaveKey('role_gender');
});

// 23-0e: the word in its line is highlighted by the server's place of it. Catches a highlight counted in bytes
// (off by the Cyrillic and the typographic apostrophe) and a line picked that does not contain the word.
it('places a word in its line in characters, in the line’s own spelling', function () {
    expect(Words::spanOfTerm('lower back', 'It hurts in his lower back.'))->toBe([16, 10])
        ->and(Words::spanOfTerm('painkiller', 'I’ve got a ‘painkiller’ here'))->toBe([12, 10])
        ->and(Words::spanOfTerm('prescription', 'Two prescriptions, please.'))->toBe([4, 13])
        ->and(Words::spanOfTerm('scope', 'Nothing here'))->toBeNull();

    $usage = WordUsage::of(spokenLesson(), 'v5', 'painkiller');
    expect($usage['speaker'])->toBe(Speaker::Partner)
        ->and($usage['step'])->toBe(6)
        ->and(mb_substr($usage['text'], $usage['offset'], $usage['length']))->toBe('painkiller')
        ->and(WordUsage::of(spokenLesson(), 'v4', 'prescription'))->toBeNull();
});
