<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Shared\Domain\ValueObject\CollectionId;

/**
 * Ask the model for an independent rendering of these terms and report where it disagrees with what
 * is stored — the translation QA sweep, run by hand before a release.
 *
 * It exists as a command of its own because it used to be part of every станок call: `enrich_pack.v2`
 * bought a `back_translation` and `language_notes` on every term, forever, for a diagnostic that is
 * read a handful of times a year (audit A5). The станок now buys only what it stores.
 */
final readonly class AuditTranslations
{
    /**
     * @param  list<CollectionId>  $collectionIds  what to audit; empty is refused by the caller —
     *         there is deliberately no audit-everything mode, because a sweep nobody reads is spend
     *         with no reader
     * @param  list<string>  $termIds  a narrower filter still: exactly these terms
     * @param  string|null  $model  run the audit on a DIFFERENT model than the configured core one —
     *         a second opinion is worth more when it is a second model
     * @param  bool  $dryRun  count and price the run without calling anything or writing anything
     */
    public function __construct(
        public array $collectionIds = [],
        public array $termIds = [],
        public int $limit = 0,
        public ?string $model = null,
        public bool $dryRun = false,
        public string $translationLang = 'ru',
    ) {}
}
