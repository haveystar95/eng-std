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
 * reader that filtered here would be a second copy of the rule, drifting from the first. Where a
 * term is USED is not here either — that is Collections' fact, read through its own port.
 */
interface TranslationKeyReader
{
    /**
     * @param  string  $termLang        the term side's language ('en')
     * @param  string  $translationLang the learner side's language ('ru')
     * @return list<TranslationKeyRow>
     */
    public function primaryKeys(string $termLang, string $translationLang): array;

    /**
     * Which learner languages the store actually holds primary keys in.
     *
     * Asked of the content, never of a config list: an audit that sweeps «the languages we support»
     * misses the one that arrived with a batch nobody re-configured for, and that is precisely the
     * language whose keys no one has read.
     *
     * @param  string  $termLang  the term side's language ('en')
     * @return list<string>  sorted, so two exports of the same store are diffable
     */
    public function translationLangs(string $termLang): array;
}
