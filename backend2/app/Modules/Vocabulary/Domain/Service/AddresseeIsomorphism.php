<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Domain\Service;

use App\Modules\Vocabulary\Domain\ValueObject\AddresseeMiss;

/**
 * A COARSE detector for translations that have lost the term's addressee (QA-17).
 *
 * The class of defect: a translation is not prose, it is the KEY the learner is asked to turn back
 * into the term. «Tell us about a challenge you faced» came back as «Расскажите о вызове, с которым
 * вы столкнулись» — fluent, and unanswerable, because «нам» is gone and `Tell me…`, `Tell us…` and
 * `Describe…` all fit it equally well. An honest answer then goes into an append-only log as a
 * lapse. The words that go missing are almost always the small ones that say WHO is speaking and
 * WHO is addressed, because a translation reads more smoothly without them.
 *
 * The rule, deliberately per GROUP rather than against one flat list of pronouns: a term that says
 * `us` is not rescued by a translation that happens to contain «вы». Each group is an English
 * trigger set and, per learner language, the forms that can carry it; a term trips the group when
 * it uses one of the triggers as a STANDALONE word and its translation contains none of that
 * language's counterparts. Started Russian-only, extended to Ukrainian the same way — a second
 * counterpart list per group, not a second rule.
 *
 * It is coarse ON PURPOSE and it never fixes anything. Both languages carry a person in ways this
 * cannot see — «расскажите»/«розкажіть» is already addressed, a genitive can be implied, «свій» can
 * stand in for «ваш» — so a hit is a CANDIDATE for a human to read, not a verdict. The owner
 * proof-reads the export and the accepted corrections go back through the existing apply mechanism.
 * Anything stricter would be a heuristic quietly rewriting the content it was asked to audit.
 */
final class AddresseeIsomorphism
{
    /**
     * The groups, as (name, English triggers, counterparts per learner language).
     *
     * `you` sits with `your`/`yours` because both Russian and Ukrainian mark them with the same
     * stem, and separating them would flag «расскажите нам о вашем опыте» for having no separate
     * word for `you`. For the same reason a group is a PERSON, not a case: any form of that person
     * answers it. «нам» — first person plural, dative — answers `we` as readily as it answers `us`
     * («Could we have the bill?» → «Можно **нам** счёт?»), so it belongs in both lists; the first
     * whole-store run flagged that row for a «мы» it never needed.
     *
     * The ты-forms are in `you/your` because Russian's informal second person is still the second
     * person. «На **твоём** месте я бы согласился» answers `If I were you` completely, and the same
     * run flagged it, «Если **ты** будешь усердно учиться» and «Какой у **тебя** номер?» only
     * because the rule had been taught вы-forms and not ты-forms. Ukrainian gets the same treatment
     * in the same commit: the identical defect must not read as a hit in one language and clean in
     * the other, which is what its shorter твій/тобі list used to produce.
     *
     * Each list is a whole paradigm rather than a stem, because matching is by standalone word: a
     * missing «вашем» is a false positive nobody can see, and a stem match would clear «нас» on
     * «насос». Ukrainian's `us`/`me` forms overlap Russian's for the plural («нам», «нас», «нами»
     * are spelled identically); the rest of each list is that language's own.
     *
     * @var list<array{name: string, triggers: list<string>, counterparts: array<string, list<string>>}>
     */
    private const GROUPS = [
        [
            'name' => 'us/me',
            'triggers' => ['us', 'me'],
            'counterparts' => [
                'ru' => ['нам', 'нас', 'нами', 'мне', 'меня', 'мной', 'мною', 'я'],
                'uk' => ['нам', 'нас', 'нами', 'мені', 'мене', 'мною', 'я'],
            ],
        ],
        [
            'name' => 'you/your',
            'triggers' => ['you', 'your', 'yours'],
            'counterparts' => [
                'ru' => [
                    'вы', 'вас', 'вам', 'вами', 'ваш', 'ваша', 'ваше', 'ваши', 'вашего', 'вашему',
                    'вашей', 'вашем', 'вашим', 'вашими', 'ваших', 'вашу',
                    'ты', 'тебя', 'тебе', 'тобой', 'тобою', 'твой', 'твоя', 'твоё', 'твое', 'твои',
                    'твоего', 'твоему', 'твоей', 'твоём', 'твоем', 'твоим', 'твоими', 'твоих', 'твою',
                ],
                'uk' => [
                    'ви', 'вас', 'вам', 'вами', 'ваш', 'ваша', 'ваше', 'ваші', 'вашого', 'вашому',
                    'вашій', 'вашим', 'вашими', 'ваших', 'вашу',
                    'ти', 'тебе', 'тобі', 'тобою', 'твій', 'твоя', 'твоє', 'твої', 'твого', 'твоєму',
                    'твоїй', 'твоїм', 'твоїми', 'твоїх', 'твою',
                ],
            ],
        ],
        [
            'name' => 'we/our',
            'triggers' => ['we', 'our', 'ours'],
            'counterparts' => [
                'ru' => ['мы', 'нам', 'нас', 'нами', 'наш', 'наша', 'наше', 'наши', 'нашего', 'нашему', 'нашей', 'нашем', 'нашим', 'нашими', 'наших', 'нашу'],
                'uk' => ['ми', 'нам', 'нас', 'нами', 'наш', 'наша', 'наше', 'наші', 'нашого', 'нашому', 'нашій', 'нашим', 'нашими', 'наших', 'нашу'],
            ],
        ],
    ];

    /**
     * What this pair dropped: per tripped group, the source's own words that went unanswered.
     *
     * This is the rule's full answer and {@see violations()} is a projection of it. The group name
     * counts a row; the words are what makes the row readable — a proof-reader should not have to
     * re-derive by eye that `us/me` fired on `me` and not on `us`.
     *
     * @param  string  $lang  the translation's language — which counterpart list applies. A group
     *                        with no list for this language is skipped entirely (never a false hit
     *                        for a learner language the rule hasn't been taught yet).
     * @return list<AddresseeMiss>  in the order the groups are declared above
     */
    public function misses(string $source, string $translation, string $lang = 'ru'): array
    {
        $out = [];
        foreach (self::GROUPS as $group) {
            $counterparts = $group['counterparts'][$lang] ?? null;
            if ($counterparts === null) {
                continue;
            }
            $used = $this->matched($source, $group['triggers']);
            if ($used === []) {
                continue; // the source never addresses anyone in this group
            }
            if ($this->containsAny($translation, $counterparts)) {
                continue; // …and the translation carries it
            }
            $out[] = new AddresseeMiss($group['name'], $used, $counterparts);
        }

        return $out;
    }

    /**
     * The groups this pair trips — empty when the translation carries everything the term addresses.
     *
     * @param  string  $lang  the translation's language, as in {@see misses()}
     * @return list<string>  group names, in the order declared above
     */
    public function violations(string $source, string $translation, string $lang = 'ru'): array
    {
        return array_map(
            static fn (AddresseeMiss $miss): string => $miss->group,
            $this->misses($source, $translation, $lang),
        );
    }

    /**
     * Every group name, so a report can list the ones that found nothing as well.
     *
     * @return list<string>
     */
    public static function groupNames(): array
    {
        return array_map(static fn (array $g): string => $g['name'], self::GROUPS);
    }

    /**
     * The learner languages the rule has counterpart lists for.
     *
     * A sweep over the whole store has to be able to say WHY a language came back with nothing. Zero
     * candidates in a language the rule has never been taught is not a clean language — it is a
     * language the rule stays silent in, by the same design that keeps it from false-hitting there.
     * Without this, a report would present the two as the same fact.
     *
     * @return list<string>
     */
    public static function languages(): array
    {
        $langs = [];
        foreach (self::GROUPS as $group) {
            foreach (array_keys($group['counterparts']) as $lang) {
                $langs[$lang] = true;
            }
        }

        return array_keys($langs);
    }

    /** Does the rule have counterpart lists for this learner language at all? */
    public static function knowsLanguage(string $lang): bool
    {
        return in_array($lang, self::languages(), true);
    }

    /**
     * Is any of [$words] present as a STANDALONE word? — the yes/no half of {@see matched()}.
     *
     * @param  list<string>  $words
     */
    private function containsAny(string $haystack, array $words): bool
    {
        return $this->matched($haystack, $words) !== [];
    }

    /**
     * Which of [$words] are present as STANDALONE words, in the order given.
     *
     * Standalone is the whole point: `us` lives inside «campus», «because» and «discuss», and «я»
     * lives inside almost every Russian verb. A substring search would flag or clear nearly
     * everything, which is a detector that says nothing at all.
     *
     * @param  list<string>  $words
     * @return list<string>
     */
    private function matched(string $haystack, array $words): array
    {
        $found = [];
        foreach ($words as $word) {
            // \p{L} rather than \b: the subject side is Cyrillic, where PCRE's word boundary is only
            // right with the /u flag AND the unicode property class — «нас» must not match «насос».
            if (preg_match('/(?<![\p{L}\p{N}])' . preg_quote($word, '/') . '(?![\p{L}\p{N}])/iu', $haystack) === 1) {
                $found[] = $word;
            }
        }

        return $found;
    }
}
