<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Query;

use App\Modules\Vocabulary\Application\Dto\TranslationKeyRow;

/**
 * Every term with its PRIMARY translation — the string the learner is actually asked, and therefore
 * the only one an audit of the question can judge.
 *
 * It returns rows and judges nothing. What counts as a defective key is
 * {@see \App\Modules\Vocabulary\Domain\Service\AddresseeIsomorphism}'s to say, in one place; a
 * reader that filtered here would be a second copy of the rule, drifting from the first.
 */
interface TranslationKeyReader
{
    /**
     * @param  string  $termLang        the term side's language ('en')
     * @param  string  $translationLang the learner side's language ('ru')
     * @return list<TranslationKeyRow>
     */
    public function primaryKeys(string $termLang, string $translationLang): array;
}
