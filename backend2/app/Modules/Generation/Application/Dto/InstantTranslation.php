<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * One machine translation, with the thing the free plan is actually billed in beside it.
 *
 * `characters` is the length of what was SENT, not of what came back — that is how the vendor
 * counts, and a ledger that measured the reply would drift from the quota it exists to protect.
 */
final readonly class InstantTranslation
{
    public function __construct(
        public string $text,
        public string $provider,
        public int $characters,
        /**
         * What language the provider decided the INPUT was in, lowercased («ru», «en»), or null
         * when it was not asked or would not say.
         *
         * The authority on direction, and deliberately the provider's answer rather than ours: a
         * search field is typed in either half of the learner's pair, and the only local clue —
         * the alphabet — is wrong for every language that shares the Latin one. See
         * {@see \App\Modules\Generation\Domain\Service\TranslationDirection}.
         */
        public ?string $detectedSource = null,
    ) {}
}
