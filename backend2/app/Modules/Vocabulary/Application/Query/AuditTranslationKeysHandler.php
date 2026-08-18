<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Query;

use App\Modules\Vocabulary\Application\Dto\TranslationKeyAuditView;
use App\Modules\Vocabulary\Application\Dto\TranslationKeyCandidate;
use App\Modules\Vocabulary\Domain\Service\AddresseeIsomorphism;

/**
 * The audit, judged where the rule lives. Vocabulary owns terms, translations and the definition of
 * a defective key; a caller gets candidates, not rows to re-judge with a copy of the rule.
 */
final readonly class AuditTranslationKeysHandler
{
    public function __construct(
        private TranslationKeyReader $keys,
        private AddresseeIsomorphism $rule,
    ) {}

    public function __invoke(AuditTranslationKeys $query): TranslationKeyAuditView
    {
        $rows = $this->keys->primaryKeys($query->termLang, $query->sourceLang);

        $candidates = [];
        foreach ($rows as $row) {
            $groups = $this->rule->violations($row->termText, $row->translation);
            if ($groups !== []) {
                $candidates[] = new TranslationKeyCandidate(
                    termId: $row->termId,
                    termText: $row->termText,
                    translation: $row->translation,
                    groups: $groups,
                );
            }
        }

        return new TranslationKeyAuditView(
            seen: count($rows),
            candidates: $candidates,
            groupNames: AddresseeIsomorphism::groupNames(),
        );
    }
}
