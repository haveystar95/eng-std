<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Dto\GeneratedItem;
use App\Modules\Generation\Application\Dto\GenerationBrief;
use App\Modules\Generation\Application\Service\LanguageBarrier;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use Tests\Doubles\ScriptedTranslationRepairer;

/** ru→en, the only language pair that exists. */
function ruEnBrief(): GenerationBrief
{
    return new GenerationBrief(
        prompt: 'фразовые глаголы',
        sourceLang: new LanguageCode('ru'),
        targetLang: new LanguageCode('en'),
        levels: ['B1'],
        size: 10,
    );
}

function item(string $translation, ?string $exampleTranslation = 'Он не сдастся.', string $text = 'give up'): GeneratedItem
{
    return new GeneratedItem(
        text: $text,
        type: 'phrasal_verb',
        translation: $translation,
        example: "I won't give up until I've achieved my goals.",
        cefr: 'B1',
        transcription: 'ɡɪv ʌp',
        exampleTranslation: $exampleTranslation,
    );
}

it('lets a clean item through without spending a call', function () {
    $repairer = new ScriptedTranslationRepairer([]);
    $out = (new LanguageBarrier($repairer))->screen([item('сдаваться, бросать')], ruEnBrief());

    expect($out->items)->toHaveCount(1)
        ->and($out->rejections)->toBe([])
        ->and($repairer->calls)->toBe(0);
});

/**
 * Outcome one: the retry works. This is the case the whole repair loop exists for — the item is
 * kept, its ENGLISH side is untouched, and only the Russian fields are the repaired ones.
 */
it('re-asks for a tainted translation and keeps the item when the answer comes back clean', function () {
    $repairer = new ScriptedTranslationRepairer(['сдаваться, бросать']);
    $out = (new LanguageBarrier($repairer))->screen([item('здаватися, відкладати на потім')], ruEnBrief());

    expect($repairer->calls)->toBe(1)
        ->and($out->rejections)->toBe([])
        ->and($out->items)->toHaveCount(1);

    $kept = $out->items[0];
    expect($kept->translation)->toBe('сдаваться, бросать')
        ->and($kept->exampleTranslation)->toBe('сдаваться, бросать (пример)')
        // The English side is not the repairer's to change, and an item whose example moved would
        // orphan every distractor built from it.
        ->and($kept->text)->toBe('give up')
        ->and($kept->example)->toBe("I won't give up until I've achieved my goals.")
        ->and($kept->cefr)->toBe('B1');
});

/**
 * Outcome two: the retries do not work. Two attempts, then the item is dropped WITH a reason —
 * the whole point being that an under-delivered collection can be told apart from a poisoned one.
 */
it('drops the item after MAX_ATTEMPTS and records why', function () {
    $repairer = new ScriptedTranslationRepairer(['відкладати', 'відхилити']);
    $out = (new LanguageBarrier($repairer))->screen([item('на одній хвилі')], ruEnBrief());

    expect($repairer->calls)->toBe(LanguageBarrier::MAX_ATTEMPTS)
        ->and($out->items)->toBe([])
        ->and($out->rejections)->toHaveCount(1);

    $rejection = $out->rejections[0];
    expect($rejection->text)->toBe('give up')
        ->and($rejection->field)->toBe('translation')
        ->and($rejection->attempts)->toBe(LanguageBarrier::MAX_ATTEMPTS)
        ->and($rejection->reason)->toContain('і')
        ->and($rejection->reason)->toContain('ru');
});

it('asks the repairer for THIS item, with its own sentence', function () {
    $repairer = new ScriptedTranslationRepairer(['сдаваться']);
    (new LanguageBarrier($repairer))->screen([item('на одній хвилі')], ruEnBrief());

    $brief = $repairer->briefs[0];
    expect($brief->text)->toBe('give up')
        ->and($brief->sentence)->toBe("I won't give up until I've achieved my goals.")
        ->and($brief->sourceLang->value)->toBe('ru')
        ->and($brief->targetLang->value)->toBe('en');
});

it('catches a tainted example translation even when the term translation is fine', function () {
    $repairer = new ScriptedTranslationRepairer(['сдаваться']);
    $out = (new LanguageBarrier($repairer))->screen(
        [item('сдаваться', 'Я не здамся, поки не досягну своїх цілей.')],
        ruEnBrief(),
    );

    expect($repairer->calls)->toBe(1)
        ->and($out->items)->toHaveCount(1)
        ->and($out->items[0]->exampleTranslation)->toBe('сдаваться (пример)');
});

/**
 * A translation repair cannot fix an English field, so paying two model calls to find that out
 * would be paying for a known answer.
 */
it('drops a target-language offence immediately, without a repair call', function () {
    $repairer = new ScriptedTranslationRepairer([]);
    $out = (new LanguageBarrier($repairer))->screen(
        [new GeneratedItem(
            text: 'give up',
            type: 'phrasal_verb',
            translation: 'сдаваться',
            example: 'I won\'t сдаваться until I\'ve achieved my goals.',
            cefr: 'B1',
            transcription: null,
            exampleTranslation: 'Я не сдамся.',
        )],
        ruEnBrief(),
    );

    expect($repairer->calls)->toBe(0)
        ->and($out->items)->toBe([])
        ->and($out->rejections[0]->field)->toBe('example')
        ->and($out->rejections[0]->attempts)->toBe(0);
});

it('counts a transport failure as a spent attempt rather than looping', function () {
    // Both calls throw: the barrier must stop at MAX_ATTEMPTS, not retry until the API recovers.
    $repairer = new ScriptedTranslationRepairer([null, null]);
    $out = (new LanguageBarrier($repairer))->screen([item('на одній хвилі')], ruEnBrief());

    expect($repairer->calls)->toBe(LanguageBarrier::MAX_ATTEMPTS)
        ->and($out->rejections)->toHaveCount(1)
        ->and($out->repairs)->toBe([]);   // nothing came back, so nothing is billed
});

it('recovers on the second attempt after the first call fails', function () {
    $repairer = new ScriptedTranslationRepairer([null, 'сдаваться']);
    $out = (new LanguageBarrier($repairer))->screen([item('на одній хвилі')], ruEnBrief());

    expect($out->items)->toHaveCount(1)
        ->and($out->rejections)->toBe([])
        ->and($out->repairs)->toHaveCount(1);
});

it('bills every repair call, including the ones that answered in the wrong language', function () {
    $repairer = new ScriptedTranslationRepairer(['відкладати', 'відхилити']);
    $out = (new LanguageBarrier($repairer))->screen([item('на одній хвилі')], ruEnBrief());

    expect($out->repairs)->toHaveCount(2)
        ->and(array_sum(array_map(static fn ($r): int => (int) $r->tokensIn, $out->repairs)))->toBe(60);
});

it('screens each item independently — one poisoned item does not cost the others anything', function () {
    $repairer = new ScriptedTranslationRepairer(['сдаваться']);
    $out = (new LanguageBarrier($repairer))->screen(
        [
            item('ломаться', 'Моя машина сломалась.', 'break down'),
            item('на одній хвилі'),
            item('присматривать', 'Присмотри за котом.', 'look after'),
        ],
        ruEnBrief(),
    );

    expect($repairer->calls)->toBe(1)
        ->and($out->items)->toHaveCount(3)
        ->and(array_map(static fn ($i): string => $i->text, $out->items))
        ->toBe(['break down', 'give up', 'look after']);
});
