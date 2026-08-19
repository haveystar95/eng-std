<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Query;

use App\Modules\Vocabulary\Application\Dto\ExampleKeyRow;
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
     * The same question for EXAMPLE sentences: the pairs a learner is asked besides the term itself.
     *
     * `term_examples` records no language for `sentence_translation`, so the language is inferred
     * from the term's own primary translation — which is only honest while the term has exactly ONE.
     * Examples of a term translated into several languages are NOT returned here and are counted by
     * {@see examplesOfUnknownLangCount()} instead: judging them would mean guessing which language
     * the sentence was translated into, and a guess in an audit is a false hit waiting to happen.
     *
     * @param  string  $termLang        the term side's language ('en')
     * @param  string  $translationLang the learner side's language ('ru')
     * @return list<ExampleKeyRow>
     */
    public function primaryExampleKeys(string $termLang, string $translationLang): array;

    /**
     * How many example pairs the sweep had to skip because their language cannot be inferred.
     *
     * Reported so the export can say it out loud: an audit that silently drops rows reads as
     * «checked everything» to the person holding it.
     */
    public function examplesOfUnknownLangCount(string $termLang): int;

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
