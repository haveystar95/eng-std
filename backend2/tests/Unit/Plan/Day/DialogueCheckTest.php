<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Check\Dialogue\DialogueCheck;
use App\Modules\Plan\Domain\Check\Dialogue\DialogueRule;

/*
 * THE CHECK OF THE DIALOGUE (наряд GEN-4, 3.6) — every rule on the canon day with ONE defect put in: the canon is clean
 * (`CanonDayTest`), the defect is what the rule finds, at the card it stands at (`x3`, `A3` / `B3`, `x3.check`, `L2`, or the
 * dialogue as a whole). Fatal or not by the order.
 */

it('asks the dialogue again for exactly the order\'s fatal rules, and repairs the cards of the rest', function () {
    $fatal = array_values(array_map(static fn (DialogueRule $r): string => $r->code(), array_filter(DialogueCheck::rules(), static fn (DialogueRule $r): bool => $r->fatal())));

    expect($fatal)->toBe([
        'dialogue.count', 'exchange.shape', 'partner.missing', 'partner.twice', 'partner.changed', 'partner.unlinked', 'line.ne_frame',
        'line.unknown_frame', 'line.foreign_filler', 'frame.unused', 'rescue.count', 'check.missing', 'check.shape', 'listening.count',
        'listening.shape',
    ]);
});

it('dialogue.count — an exchange fewer than DIALOGUE_COUNT, or the steps out of their run', function (Closure $edit) {
    expect(dialogueFound(dayCanonDialogue($edit)))->toContain('dialogue.count@dialogue');
})->with([
    'an exchange fewer' => [static function (array $raw): array {
        $raw['dialogue'] = array_values(array_filter($raw['dialogue'], static fn (array $e): bool => $e['step'] !== 7));

        return $raw;
    }],
    'a gap' => [dcAt(8, static fn (array $e): array => [...$e, 'step' => 9])],
]);

it('exchange.shape — an exchange the other one opens, or of one message', function (Closure $edit) {
    expect(dialogueFound(dayCanonDialogue($edit)))->toContain('exchange.shape@x3');
})->with([
    'opened by the learner' => [dcAt(3, static fn (array $e): array => [...$e, 'messages' => array_reverse($e['messages'])])],
    'one message' => [dcAt(3, static fn (array $e): array => [...$e, 'messages' => array_slice($e['messages'], 0, 1)])],
]);

it('partner.missing — a partner line of the skeleton no exchange carries', function () {
    expect(dialogueFound(dayCanonDialogue(dcAt(5, static fn (array $e): array => [...$e, 'partner_line' => null]))))
        ->toContain('partner.missing@dialogue');
});

it('partner.twice — a partner line two exchanges carry', function () {
    expect(dialogueFound(dayCanonDialogue(dcAt(7, static fn (array $e): array => [...$e, 'partner_line' => 'a6']))))
        ->toContain('partner.twice@x7');
});

it('partner.changed — a partner line said otherwise than the skeleton spells it, or no line of the skeleton', function (Closure $edit) {
    expect(dialogueFound(dayCanonDialogue($edit)))->toContain('partner.changed@A3');
})->with([
    'the target' => [dcSaid(3, 'A', static fn (array $m): array => [...$m, 'text_target' => 'Unde ați lucrat până acum?'])],
    'the native' => [dcSaid(3, 'A', static fn (array $m): array => [...$m, 'text_native' => 'Где вы работали до этого?'])],
    'no such line' => [dcAt(3, static fn (array $e): array => [...$e, 'partner_line' => 'a9'])],
]);

it('partner.changed — forgives the spaces around a line, nothing else', function () {
    expect(dialogueFound(dayCanonDialogue(dcSaid(3, 'A', static fn (array $m): array => [...$m, 'text_target' => ' Unde ați lucrat înainte? ']))))
        ->not->toContain('partner.changed@A3');
});

it('partner.unlinked — an A line of the dialogue\'s own beside a frame a partner line pairs with', function () {
    expect(dialogueFound(dayCanonDialogue(dcAt(4, static fn (array $e): array => [...$e, 'partner_line' => null]))))
        ->toContain('partner.unlinked@A4');
});

it('partner.unlinked — lets the dialogue write the A line of a frame no partner line pairs with', function () {
    $skeleton = dayCanonSkeleton(scAt('partner_lines', 'a7', static fn (array $l): array => [...$l, 'pairs_with' => [6]]));
    $dialogue = dayCanonDialogue(dcAt(8, static function (array $e): array {
        $e['partner_line'] = null;
        $e['must_understand'] = null;
        $e['messages'][1]['text_target'] = 'De luni până vineri.';
        $e['messages'][1]['text_native'] = 'С понедельника по пятницу.';

        return $e;
    }));

    expect(dialogueFound($dialogue, dayCanonDialogueContext($skeleton)))->not->toContain('partner.unlinked@A8');
});

it('line.ne_frame — a learner line that is not its frame with its filler, in either language', function (Closure $edit) {
    expect(dialogueFound(dayCanonDialogue($edit)))->toContain('line.ne_frame@B3');
})->with([
    'a word dropped' => [dcSaid(3, 'B', static fn (array $m): array => [...$m, 'text_target' => 'Am lucrat la magazin'])],
    'a word added' => [dcSaid(3, 'B', static fn (array $m): array => [...$m, 'text_native' => 'Я работал в большом магазине'])],
    'a glue without its comma' => [dcSaid(3, 'B', static fn (array $m): array => [...$m, 'text_target' => 'Da am lucrat la un magazin'])],
]);

it('line.ne_frame — forgives a glue that ends in a comma, the case of the first letter and the closing mark', function (string $target) {
    expect(dialogueFound(dayCanonDialogue(dcSaid(3, 'B', static fn (array $m): array => [...$m, 'text_target' => $target]))))
        ->not->toContain('line.ne_frame@B3');
})->with([
    'a glue' => ['Da, am lucrat la un magazin.'],
    'a full stop' => ['Am lucrat la un magazin.'],
]);

it('line.unknown_frame — a learner line on a frame the skeleton does not have', function () {
    expect(dialogueFound(dayCanonDialogue(dcSaid(3, 'B', static fn (array $m): array => [...$m, 'phrase_id' => 'p9']))))
        ->toContain('line.unknown_frame@B3');
});

it('line.foreign_filler — a filler that is not its frame\'s, or a filler of a frame without a slot', function (Closure $edit, string $at) {
    expect(dialogueFound(dayCanonDialogue($edit)))->toContain("line.foreign_filler@{$at}");
})->with([
    'not the frame\'s' => [dcSaid(3, 'B', static fn (array $m): array => [...$m, 'filler' => 'o fabrică', 'text_target' => 'Am lucrat la o fabrică', 'text_native' => 'Я работал на фабрике']), 'B3'],
    'no slot' => [dcSaid(8, 'B', static fn (array $m): array => [...$m, 'filler' => 'program']), 'B8'],
]);

it('frame.unused — a frame of the skeleton no learner line stands on', function () {
    $dialogue = dayCanonDialogue(dcSaid(5, 'B', static fn (array $m): array => [
        ...$m, 'phrase_id' => 'p3', 'filler' => 'un birou', 'text_target' => 'Am lucrat la un birou', 'text_native' => 'Я работал в офисе',
    ]));

    expect(dialogueFound($dialogue))->toContain('frame.unused@dialogue');
});

it('rescue.count — no rescue, or two', function (Closure $edit) {
    expect(dialogueFound(dayCanonDialogue($edit)))->toContain('rescue.count@dialogue');
})->with([
    'none' => [dcAt(7, static fn (array $e): array => [...$e, 'kind' => 'ask'])],
    'two' => [dcAt(5, static fn (array $e): array => [...$e, 'kind' => 'rescue'])],
]);

it('check.missing — an exchange without its check', function () {
    $empty = ['text_target' => '', 'text_native' => '', 'options' => [], 'correct_option_index' => 0, 'explanation_native' => ''];

    expect(dialogueFound(dayCanonDialogue(dcAt(2, static fn (array $e): array => [...$e, 'check' => $empty]))))
        ->toContain('check.missing@x2.check')
        ->not->toContain('check.shape@x2.check');
});

it('check.shape — two options, an option in one language only, the right one out of the list', function (Closure $edit) {
    expect(dialogueFound(dayCanonDialogue(dcAt(2, $edit))))->toContain('check.shape@x2.check');
})->with([
    'two options' => [static fn (array $e): array => [...$e, 'check' => [...$e['check'], 'options' => array_slice($e['check']['options'], 0, 2)]]],
    'one language' => [static function (array $e): array {
        $e['check']['options'][2]['text_native'] = '';

        return $e;
    }],
    'out of the list' => [static fn (array $e): array => [...$e, 'check' => [...$e['check'], 'correct_option_index' => 3]]],
]);

it('listening.count — fewer than three questions, or more than five', function (int $count) {
    $dialogue = dayCanonDialogue(static function (array $raw) use ($count): array {
        $questions = $raw['listening']['questions'];
        $raw['listening']['questions'] = array_slice([...$questions, ...$questions], 0, $count);

        return $raw;
    });

    expect(dialogueFound($dialogue))->toContain('listening.count@dialogue');
})->with([2, 6]);

it('listening.shape — a question of two options, or with its right one out of the list', function (Closure $edit) {
    expect(dialogueFound(dayCanonDialogue(dcHeard(2, $edit))))->toContain('listening.shape@L2');
})->with([
    'two options' => [static fn (array $q): array => [...$q, 'options_native' => array_slice($q['options_native'], 0, 2)]],
    'out of the list' => [static fn (array $q): array => [...$q, 'correct_option_index' => -1]],
]);

it('check.answer_is_filler — a check whose right option is the learner\'s own filler', function (array $option) {
    $dialogue = dayCanonDialogue(dcAt(3, static function (array $e) use ($option): array {
        $e['check']['options'][0] = $option;

        return $e;
    }));

    expect(dialogueFound($dialogue))->toContain('check.answer_is_filler@x3.check');
})->with([
    'in the target' => [['text_target' => 'Un magazin', 'text_native' => 'Торговля']],
    'in the native' => [['text_target' => 'Comerț', 'text_native' => 'Магазине']],
]);

it('check.verbatim — a right option that repeats two words in a row of A\'s line', function (array $option) {
    $dialogue = dayCanonDialogue(dcAt(3, static function (array $e) use ($option): array {
        $e['check']['options'][0] = $option;

        return $e;
    }));

    expect(dialogueFound($dialogue))->toContain('check.verbatim@x3.check');
})->with([
    'in the target' => [['text_target' => 'Unde ați lucrat', 'text_native' => 'Прежнее место работы']],
    'in the native' => [['text_target' => 'Locul de muncă anterior', 'text_native' => 'Где вы работали']],
]);

it('native.foreign_letters — a Latin word in a native field of a check or of the listening', function (Closure $edit, string $at) {
    expect(dialogueFound(dayCanonDialogue($edit)))->toContain("native.foreign_letters@{$at}");
})->with([
    'an explanation' => [dcAt(4, static fn (array $e): array => [...$e, 'check' => [...$e['check'], 'explanation_native' => 'Интервьюер спрашивает про durata.']]), 'x4.check'],
    'an option' => [dcHeard(1, static fn (array $q): array => [...$q, 'options_native' => ['Clienți', 'Документами', 'Товаром']]), 'L1'],
]);

it('listening.distractor_not_filler — a wrong option that is no other filler of the frame whose value the question asks', function () {
    expect(dialogueFound(dayCanonDialogue(dcHeard(3, static fn (array $q): array => [...$q, 'options_native' => ['Два года', 'Шесть месяцев', 'Пять лет']]))))
        ->toContain('listening.distractor_not_filler@L3');
});

it('line.too_long — a learner line of more than ten words, its glue aside', function () {
    $long = 'Am lucrat la un magazin mare de haine din centrul orașului nostru';

    expect(dialogueFound(dayCanonDialogue(dcSaid(3, 'B', static fn (array $m): array => [...$m, 'text_target' => $long]))))
        ->toContain('line.too_long@B3');
});

it('speaking_key.wrong — a key that is not in its line, or holds a word of the filler', function (string $key) {
    expect(dialogueFound(dayCanonDialogue(dcSaid(3, 'B', static fn (array $m): array => [...$m, 'speaking_key' => $key]))))
        ->toContain('speaking_key.wrong@B3');
})->with([
    'not in the line' => ['Am muncit'],
    'the filler\'s word' => ['lucrat la un'],
]);

it('variant.longer — a simplified variant of more words than its line', function () {
    expect(dialogueFound(dayCanonDialogue(dcSaid(4, 'B', static fn (array $m): array => [...$m, 'simplified_variants' => ['Am lucrat acolo timp de doi ani']]))))
        ->toContain('variant.longer@B4');
});
