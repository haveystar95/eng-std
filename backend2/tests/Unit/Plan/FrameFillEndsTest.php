<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\Service\SpokenLines;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;

/**
 * A FILLER THAT ENDS WITH AN ABBREVIATION'S DOT CLOSES THE SENTENCE WITH IT (наряд FIX-4 §6): the vet's day 1 had five
 * cards «…I can come at 3 p.m..» — the frame's full stop after the filler's own. When the language's rule of sentence
 * ends reads the filler's last dot as an abbreviation's and the frame has only its full stop after the window, one dot is
 * left — in each language by its own pack.
 */

// Canon (§6): «наполнение кончается точкой сокращения из пакета (SentenceEnds) и суффикс каркаса — точка — остаётся одна»,
// both languages. CATCHES «p.m..», a dot taken from a frame that goes on after its window, and a sentence-ending filler
// («home.» — the validator's finding, not an abbreviation) quietly mended here.
it('leaves one dot when the filler ends with an abbreviation and the frame ends with its full stop', function () {
    $en = lessonPacks()->for('en')->sentenceEnds();
    $ru = lessonPacks()->for('ru')->sentenceEnds();

    expect(FrameText::fill('I can come at ___.', '3 p.m.', $en))->toBe('I can come at 3 p.m.')
        ->and(FrameText::fill('I can come at ___ .', '3 p.m.', $en))->toBe('I can come at 3 p.m.')
        ->and(FrameText::fill('Ask for ___.', 'Dr. Smith', $en))->toBe('Ask for Dr. Smith.')
        ->and(FrameText::fill('Come at ___ today.', '3 p.m.', $en))->toBe('Come at 3 p.m. today.')
        ->and(FrameText::fill('Can you come at ___?', '3 p.m.', $en))->toBe('Can you come at 3 p.m.?')
        ->and(FrameText::fill('I work from ___.', 'home.', $en))->toBe('I work from home..')
        ->and(FrameText::fill('Я приеду в ___.', '2027 г.', $ru))->toBe('Я приеду в 2027 г.')
        ->and(FrameText::nativeSentence('Я приеду в ___.', '2027 г.', 'Я приеду.', $ru))->toBe('Я приеду в 2027 г.')
        // A language whose pack says nothing of its sentence ends knows no abbreviation.
        ->and(FrameText::fill('I can come at ___.', '3 p.m.', null))->toBe('I can come at 3 p.m..');
});

// Canon (§6): the phrase's own text and its fillers' sentences are put together by ONE rule — the filler the phrase is
// said with is its file, not a second one (TTS-2). CATCHES a term «…p.m.» beside a filler sentence «…p.m..», which would
// buy the phrase's own sentence again under `p5.f1`.
it('writes the phrase and its fillers by the same rule, so the phrase keeps its own file', function () {
    $request = FakePlanModel::lessonRequest('x');
    $payload = FakePlanModel::lessonPayload($request);
    foreach ($payload['phrases'] as $i => $phrase) {
        if ($phrase['id'] === 'p5') {
            $payload['phrases'][$i]['slot']['fillers'][0]['target'] = 'until 3 p.m.';
        }
    }
    foreach ($payload['dialogue'] as $i => $exchange) {
        foreach ($exchange['messages'] as $m => $message) {
            if (($message['phrase_id'] ?? null) === 'p5') {
                $payload['dialogue'][$i]['messages'][$m]['filler'] = 'until 3 p.m.';
                $payload['dialogue'][$i]['messages'][$m]['text_target'] = 'Okay, he will rest until 3 p.m..';
            }
        }
    }
    $terms = planTermsOf(PlanSceneId::fromString('01M2F1X4SCENE0000000000001'), (new LessonParser)->parse($payload));
    $p5 = array_values(array_filter($terms, static fn ($t): bool => $t->ref() === 'p5'))[0];
    $fillers = SpokenLines::fillers($p5, lessonPacks()->for('en')->sentenceEnds());

    expect($p5->textTarget())->toBe('He will rest until 3 p.m.')
        ->and($fillers[0]['text'])->toBe('He will rest until 3 p.m.')
        ->and($fillers[0]['voicedAs'])->toBe('p5');
});
