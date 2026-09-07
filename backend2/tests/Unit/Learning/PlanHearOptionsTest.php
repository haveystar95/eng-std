<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\PlanHearOptions;
use App\Modules\Learning\Domain\ValueObject\SituationalCandidate;

/**
 * Правило «из чего бывают варианты такта „что тебе сказали?“», без базы — наряд DAY-FIX-3, Ч.2.2.
 */
function hearLine(string $id, string $text, ?string $skill = 's1', string $shelf = 'hear'): SituationalCandidate
{
    return new SituationalCandidate($id, $shelf, $skill, $text);
}

it('берёт только реплики роли, и только ДРУГОЙ функции', function () {
    $scenes = [1 => [
        hearLine('same-skill', 'How long have you had the pain?', 's1'),
        hearLine('other-skill', 'Do you take any medication?', 's2'),
        hearLine('say', 'It hurts in my back.', 's2', 'say'),
        hearLine('word', 'medication', 's2', 'words'),
    ]];

    $ids = PlanHearOptions::forLine(hearLine('target', 'Where does it hurt?', 's1'), $scenes, 1);

    expect($ids)->toBe(['other-skill']);
});

it('два приглашения — одна функция: перефразы «Anything else?» / «Do you have any questions?» не стоят рядом', function () {
    // Живой прогон владельца: оба варианта такта 1 были приглашениями, и правильного среди них
    // не было по смыслу.
    $scenes = [1 => [
        hearLine('invite-2', 'Do you have any questions?', 's2'),
        hearLine('question', 'How long have you had the pain?', 's3'),
    ]];
    $functions = ['target' => PlanHearOptions::FUNCTION_INVITATION, 'invite-2' => PlanHearOptions::FUNCTION_INVITATION];

    $ids = PlanHearOptions::forLine(hearLine('target', 'Anything else?', 's1'), $scenes, 1, $functions);

    expect($ids)->toBe(['question']);
});

it('выбрасывает кандидата, у которого половина слов общие с правильной репликой', function () {
    $scenes = [1 => [
        // 3 из 5 слов общие с целью — перефраз.
        hearLine('paraphrase', 'What seems to be the problem?', 's2'),
        // 3 из 8 — нет.
        hearLine('distinct', 'Do you have any other questions for me?', 's3'),
    ]];

    $ids = PlanHearOptions::forLine(hearLine('target', 'What is the problem today?', 's1'), $scenes, 1);

    expect($ids)->toBe(['distinct']);
});

it('из двух кандидатов, которые перефразы друг друга, остаётся первый', function () {
    $scenes = [1 => [
        hearLine('a', 'Do you take any medication?', 's2'),
        hearLine('b', 'Do you take any pills?', 's3'),
        hearLine('c', 'Is the pain sharp or dull?', 's4'),
    ]];

    $ids = PlanHearOptions::forLine(hearLine('target', 'Where does it hurt?', 's1'), $scenes, 1);

    expect($ids)->toBe(['a', 'c']);
});

it('своя сцена первой, потом реплики роли из других сцен плана', function () {
    $scenes = [
        1 => [hearLine('day1', 'Could you tell me about your background?', 's1')],
        2 => [hearLine('own', 'Do you take any medication?', 's2')],
        3 => [hearLine('day3', 'When can you start?', 's3')],
    ];

    $ids = PlanHearOptions::forLine(hearLine('target', 'Where does it hurt?', 's9'), $scenes, 2);

    expect($ids)->toBe(['own', 'day1', 'day3']);
});

it('ставит реплику, уже прозвучавшую в этом разговоре, последней и не берёт кандидата без функции', function () {
    $scenes = [1 => [
        hearLine('heard', 'Do you take any medication?', 's2'),
        hearLine('nameless', 'Is the pain sharp or dull?', null),
        hearLine('fresh', 'When did it start?', 's3'),
    ]];

    $ids = PlanHearOptions::forLine(hearLine('target', 'Where does it hurt?', 's1'), $scenes, 1, alreadyHeard: ['heard']);

    expect($ids)->toBe(['fresh', 'heard']);
});

it('у последнего такта сцены из одной сцены пул не пуст — прозвучавшие реплики остаются кандидатами', function () {
    $scenes = [1 => [
        hearLine('first', 'Where does it hurt?', 's1'),
        hearLine('second', 'What is the problem today?', 's2'),
        hearLine('last', 'Do you have any other questions for me?', 'ask'),
    ]];

    $ids = PlanHearOptions::forLine(hearLine('last', 'Do you have any other questions for me?', 'ask'), $scenes, 1, alreadyHeard: ['first', 'second']);

    expect($ids)->toBe(['first', 'second']);
});
