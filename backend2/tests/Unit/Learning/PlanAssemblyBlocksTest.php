<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\PlanAssemblyBlocks;
use App\Modules\Shared\Domain\Service\DistractorLength;

it('breaks a line into its own blocks and leaves punctuation on its word', function () {
    // Человек собирает фразу, а не расставляет запятые: отдельный блок «,» был бы вопросом про
    // типографику, которого карточка не задаёт.
    expect(PlanAssemblyBlocks::own('Sure, happy to.'))->toBe(['Sure,', 'happy', 'to.'])
        ->and(PlanAssemblyBlocks::own('   '))->toBe([]);
});

it('takes decoy blocks from the plan’s words and connectors, and only similar ones', function () {
    $own = PlanAssemblyBlocks::own('I work in design');
    $pool = ['front desk', 'responsibility', 'now', 'appointment'];

    $decoys = PlanAssemblyBlocks::decoys($own, $pool, new DistractorLength());

    // `responsibility` (14) не помещается в ±50 % ни от одного своего блока (1…6 букв) — это не
    // дистрактор, а подсказка о том, какие блоки лишние.
    expect($decoys)->not->toContain('responsibility')
        ->and($decoys)->toContain('now');
});

it('never offers a block the line already has', function () {
    $own = PlanAssemblyBlocks::own('I work in design');

    expect(PlanAssemblyBlocks::decoys($own, ['work', 'Work', 'design'], new DistractorLength()))
        ->toBe([]);
});

it('stops at four decoys — a board of tiles is not a search field', function () {
    $own = PlanAssemblyBlocks::own('I work in design here');
    $pool = ['now', 'here too', 'soon', 'later', 'again', 'twice'];

    expect(PlanAssemblyBlocks::decoys($own, $pool, new DistractorLength()))
        ->toHaveCount(PlanAssemblyBlocks::MAX_DECOYS);
});

it('hands back what it has when the plan is poor in words, instead of refusing the card', function () {
    // Выбор при голоде отбивается — выбор из двух это монетка. Сборка при голоде остаётся задачей:
    // порядок слов и так надо вспомнить. А отбить её нельзя — это третий шаг чек-листа ступени B,
    // и шаг, который нельзя раздать, это день, который не проходится.
    $own = PlanAssemblyBlocks::own('I work in design');

    expect(PlanAssemblyBlocks::decoys($own, [], new DistractorLength()))->toBe([])
        ->and(PlanAssemblyBlocks::decoys($own, ['now'], new DistractorLength()))->toBe(['now']);
});
