<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\PlanAnswerOptions;
use App\Modules\Learning\Domain\ValueObject\SituationalCandidate;

/**
 * Правило «из чего бывают варианты твоего хода», без базы — наряд DAY-2-FIX, Ч.1.5.
 */
function answerCandidate(string $id, ?string $shelf, ?string $skill): SituationalCandidate
{
    return new SituationalCandidate($id, $shelf, $skill, "text-{$id}");
}

it('берёт только полки, с которых человек говорит', function () {
    $scenes = [1 => [
        answerCandidate('hear', 'hear', 's2'),
        answerCandidate('say', 'say', 's2'),
        answerCandidate('ask', 'ask', 's2'),
        answerCandidate('word', 'words', 's2'),
        answerCandidate('chunk', 'chunks', 's2'),
        answerCandidate('number', 'numbers', 's2'),
        answerCandidate('rescue', 'rescue', null),
    ]];

    $ids = PlanAnswerOptions::forTurn(answerCandidate('target', 'say', 's1'), $scenes, 1);

    expect($ids)->toBe(['say', 'ask']);
});

it('выбрасывает варианты, отвечающие на тот же вопрос', function () {
    $scenes = [1 => [
        answerCandidate('same', 'say', 's1'),
        answerCandidate('other', 'say', 's2'),
    ]];

    expect(PlanAnswerOptions::forTurn(answerCandidate('target', 'say', 's1'), $scenes, 1))->toBe(['other']);
});

it('не берёт кандидата без умения — «неизвестно» это не «другой вопрос»', function () {
    $scenes = [1 => [answerCandidate('nameless', 'say', null), answerCandidate('other', 'say', 's2')]];

    expect(PlanAnswerOptions::forTurn(answerCandidate('target', 'say', 's1'), $scenes, 1))->toBe(['other']);
});

it('не берёт реплику, которая в этом разговоре уже прозвучала — любой стороной (DAY-FIX-2, Ч.1.5)', function () {
    // Живой прогон 05.09: среди вариантов стояла реплика, сказанная двумя обменами выше.
    $scenes = [1 => [
        answerCandidate('said-earlier', 'say', 's2'),
        answerCandidate('fresh', 'say', 's3'),
    ]];

    expect(PlanAnswerOptions::forTurn(answerCandidate('target', 'say', 's1'), $scenes, 1, alreadySaid: ['said-earlier']))
        ->toBe(['fresh']);
});

it('не берёт ничего, когда умения нет у самого хода', function () {
    $scenes = [1 => [answerCandidate('other', 'say', 's2')]];

    expect(PlanAnswerOptions::forTurn(answerCandidate('target', 'say', null), $scenes, 1))->toBe([]);
});

it('ставит свою сцену первой, а остальные — по порядку дней', function () {
    $scenes = [
        1 => [answerCandidate('d1', 'say', 's1')],
        2 => [answerCandidate('d2', 'say', 's1')],
        3 => [answerCandidate('d3', 'say', 's1')],
    ];

    // Ход из сцены 2: сначала её собственные, потом 1 и 3 по возрастанию.
    expect(PlanAnswerOptions::forTurn(answerCandidate('target', 'say', 's9'), $scenes, 2))
        ->toBe(['d2', 'd1', 'd3']);
});

it('никогда не предлагает сам ход', function () {
    $scenes = [1 => [answerCandidate('target', 'say', 's1'), answerCandidate('other', 'say', 's2')]];

    expect(PlanAnswerOptions::forTurn(answerCandidate('target', 'say', 's1'), $scenes, 1))->toBe(['other']);
});

it('знает, у каких полок вообще бывают такие варианты', function () {
    expect(PlanAnswerOptions::isSpokenShelf('say'))->toBeTrue()
        ->and(PlanAnswerOptions::isSpokenShelf('ask'))->toBeTrue()
        ->and(PlanAnswerOptions::isSpokenShelf('hear'))->toBeFalse()
        ->and(PlanAnswerOptions::isSpokenShelf('words'))->toBeFalse()
        ->and(PlanAnswerOptions::isSpokenShelf(null))->toBeFalse();
});
