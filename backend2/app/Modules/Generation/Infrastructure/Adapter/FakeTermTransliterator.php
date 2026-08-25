<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\TermReadingBrief;
use App\Modules\Generation\Application\Dto\TermReadingResult;
use App\Modules\Generation\Application\Port\TermTransliteratorPort;

/**
 * Deterministic reading hints — no network. Bound when GENERATION_DRIVER=fake.
 *
 * It answers in the SUPPORT language's alphabet, because a fake that failed the alphabet gate would
 * make every test of this path measure the gate instead of the path. A support language it has no
 * table for gets the term back unchanged, which is the honest «this fake cannot help you» and shows
 * up immediately as a refusal rather than as a plausible wrong hint.
 */
final class FakeTermTransliterator implements TermTransliteratorPort
{
    /** Enough Latin→Cyrillic to read like a hint; nobody is learning from it. */
    private const CYRILLIC = [
        'sch' => 'щ', 'ch' => 'ч', 'sh' => 'ш', 'th' => 'т', 'ph' => 'ф', 'ck' => 'к', 'ee' => 'и',
        'oo' => 'у', 'a' => 'а', 'b' => 'б', 'c' => 'к', 'd' => 'д', 'e' => 'е', 'f' => 'ф',
        'g' => 'г', 'h' => 'х', 'i' => 'и', 'j' => 'дж', 'k' => 'к', 'l' => 'л', 'm' => 'м',
        'n' => 'н', 'o' => 'о', 'p' => 'п', 'q' => 'к', 'r' => 'р', 's' => 'с', 't' => 'т',
        'u' => 'у', 'v' => 'в', 'w' => 'в', 'x' => 'кс', 'y' => 'й', 'z' => 'з',
    ];

    public function read(TermReadingBrief $brief): TermReadingResult
    {
        $support = strtolower(trim($brief->supportLang));
        $text = in_array($support, ['ru', 'uk'], true)
            ? strtr(mb_strtolower($brief->text), self::CYRILLIC)
            : $brief->text;

        return new TermReadingResult(
            text: $text,
            model: 'fake',
            promptVersion: 'fake-core',
            tokensIn: 30,
            tokensOut: 5,
        );
    }
}
