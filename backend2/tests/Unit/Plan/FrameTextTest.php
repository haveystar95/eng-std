<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Lesson\Filler;
use App\Modules\Plan\Domain\Lesson\Phrase;
use App\Modules\Plan\Domain\Lesson\Slot;
use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\ValueObject\ExchangeKind;

/**
 * A FRAME SAID WITH A FILLER, IN A LANGUAGE THAT OPENS ITS QUESTIONS (наряд LANG-1 §1): the Spanish ¿ ¡ stand before the
 * first letter, and the server's reading of a learner line forgives that letter's case past them — exactly what it
 * forgives an English line after its glue.
 */

/** @param list<string> $fillers */
function ftPhrase(string $frame, array $fillers): Phrase
{
    return new Phrase('p1', ExchangeKind::Ask, $frame, '___', '', new Slot('', array_map(
        static fn (string $f): Filler => new Filler($f, $f, '', true),
        $fillers,
    )));
}

// Canon (наряд LANG-1 §1): «испанская строка «Sí, ¿puedo pagar con tarjeta?» против каркаса «¿Puedo pagar con ___?» должна
// совпасть так же, как «Yes, can I …» в английском — первый знак ¿ ¡ пропускается при сравнении первой буквы». CATCHES the
// line of every Spanish question after its glue served as `line.ne_frame` (fatal) because «¿p» and «¿P» differ in their
// SECOND character, a line that drops the frame's ¿ taken for the frame, and the English reading changed.
it('matches a Spanish line after its glue, the first letter read past ¿ and ¡', function () {
    $pay = ftPhrase('¿Puedo pagar con ___?', ['tarjeta', 'efectivo']);
    $great = ftPhrase('¡Qué ___!', ['bien', 'suerte']);

    expect(FrameText::line($pay, 'Sí, ¿puedo pagar con tarjeta?'))->toMatchArray(['matches' => true, 'glue' => 'Sí, '])
        ->and(FrameText::line($pay, 'Sí, ¿puedo pagar con tarjeta?')['filler']?->target)->toBe('tarjeta')
        ->and(FrameText::line($pay, '¿Puedo pagar con efectivo?')['filler']?->target)->toBe('efectivo')
        ->and(FrameText::line($pay, 'Vale. ¿Puedo pagar con efectivo?')['matches'])->toBeTrue()
        ->and(FrameText::line($great, 'Oh, ¡qué suerte!')['filler']?->target)->toBe('suerte')
        // The opening mark is compared like every other character: a line without it is not the frame.
        ->and(FrameText::line($pay, 'Sí, puedo pagar con tarjeta?')['matches'])->toBeFalse()
        ->and(FrameText::line($pay, 'Sí, ¡puedo pagar con tarjeta?')['matches'])->toBeFalse()
        // English as it was.
        ->and(FrameText::line(ftPhrase('Can I pay ___?', ['by card', 'in cash']), 'Yes, can I pay by card?'))->toMatchArray(['matches' => true, 'glue' => 'Yes, '])
        ->and(FrameText::line(ftPhrase('Can I pay ___?', ['by card']), 'Yes, can I pay by card?')['filler']?->target)->toBe('by card')
        ->and(FrameText::line(ftPhrase('Can I pay ___?', ['by card']), '"can I pay by card?')['matches'])->toBeFalse();
});

// Canon (наряд FIX-2, п. 1 + LANG-1 §1): a native sentence starts with a capital — a Spanish one past its ¿ ¡ (Spanish is
// a learner's own language now). CATCHES «¿la farmacia está abierta?» left lower-case on a card, and a text that starts
// with another mark changed.
it('capitalises a sentence past the Spanish opening marks, and nothing else', function () {
    expect(FrameText::capitalized('¿la farmacia está abierta?'))->toBe('¿La farmacia está abierta?')
        ->and(FrameText::capitalized('¡qué bien!'))->toBe('¡Qué bien!')
        ->and(FrameText::capitalized('  моей кошке нужен ветеринар.'))->toBe('Моей кошке нужен ветеринар.')
        ->and(FrameText::capitalized('"quoted" words'))->toBe('"quoted" words')
        ->and(FrameText::capitalized('¿'))->toBe('¿')
        ->and(FrameText::nativeSentence('¿___ está abierta?', 'la farmacia', '?'))->toBe('¿La farmacia está abierta?');
});

// Canon (LINE_TOO_LONG, «10 words, not counting leading glue» + LANG-1 §1): an exclamation opened by ¡ is glue like any
// other. CATCHES «¡Claro!» counted into the line's words.
it('reads glue opened by ¡ as glue', function () {
    expect(FrameText::leadingGlue('¡Claro! ¿Puedo pagar con tarjeta?'))->toBe('¡Claro! ')
        ->and(FrameText::wordsWithoutGlue('¡Claro! ¿Puedo pagar con tarjeta?'))->toBe(4)
        ->and(FrameText::leadingGlue('Yes, I can'))->toBe('Yes, ')
        ->and(FrameText::leadingGlue('I can come'))->toBe('');
});
