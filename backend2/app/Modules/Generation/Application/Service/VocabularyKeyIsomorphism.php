<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Service;

use App\Modules\Generation\Domain\Service\KeyIsomorphism;
use App\Modules\Vocabulary\Application\Service\TranslationKeyRule;

/**
 * Generation's side of the seam: the check engine asks its own Domain interface, and this is what
 * answers it — Vocabulary's rule, reached the only legal way, through that module's Application.
 *
 * A thin class on purpose. Its whole job is that there is exactly ONE definition of a broken key in
 * this codebase, and a bake-off ranks providers by the same standard the store sweep uses.
 */
final readonly class VocabularyKeyIsomorphism implements KeyIsomorphism
{
    public function __construct(private TranslationKeyRule $rule) {}

    public function gaps(string $source, string $translation, string $lang): array
    {
        return $this->rule->gaps($source, $translation, $lang);
    }

    public function knows(string $lang): bool
    {
        return $this->rule->knows($lang);
    }
}
