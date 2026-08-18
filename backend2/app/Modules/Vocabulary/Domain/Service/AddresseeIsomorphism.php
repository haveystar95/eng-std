<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Domain\Service;

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
 * The rule, deliberately per GROUP rather than against one flat list of Russian pronouns: a term
 * that says `us` is not rescued by a translation that happens to contain «вы». Each group is an
 * English trigger set and the Russian forms that can carry it; a term trips the group when it uses
 * one of the triggers as a STANDALONE word and its translation contains none of the counterparts.
 *
 * It is coarse ON PURPOSE and it never fixes anything. Russian carries a person in ways this cannot
 * see — «расскажите» is already addressed, a genitive can be implied, «свой» can stand in for «ваш»
 * — so a hit is a CANDIDATE for a human to read, not a verdict. The owner proof-reads the export and
 * the accepted corrections go back through the existing apply mechanism. Anything stricter would be
 * a heuristic quietly rewriting the content it was asked to audit.
 */
final class AddresseeIsomorphism
{
    /**
     * The groups, as (name, English triggers, Russian counterparts).
     *
     * `you` sits with `your`/`yours` because Russian marks them with the same stem, and separating
     * them would flag «расскажите нам о вашем опыте» for having no separate word for `you`.
     *
     * @var list<array{name: string, triggers: list<string>, counterparts: list<string>}>
     */
    private const GROUPS = [
        [
            'name' => 'us/me',
            'triggers' => ['us', 'me'],
            'counterparts' => ['нам', 'нас', 'мне', 'меня', 'я'],
        ],
        [
            'name' => 'you/your',
            'triggers' => ['you', 'your', 'yours'],
            'counterparts' => ['вы', 'вас', 'вам', 'ваш', 'ваша', 'ваше', 'ваши'],
        ],
        [
            'name' => 'we/our',
            'triggers' => ['we', 'our', 'ours'],
            'counterparts' => ['мы', 'наш', 'наша', 'наше', 'наши'],
        ],
    ];

    /**
     * The groups this pair trips — empty when the translation carries everything the term addresses.
     *
     * @return list<string>  group names, in the order declared above
     */
    public function violations(string $term, string $translation): array
    {
        $out = [];
        foreach (self::GROUPS as $group) {
            if (! $this->containsAny($term, $group['triggers'])) {
                continue; // the term never addresses anyone in this group
            }
            if ($this->containsAny($translation, $group['counterparts'])) {
                continue; // …and the translation carries it
            }
            $out[] = $group['name'];
        }

        return $out;
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
     * Is any of [$words] present as a STANDALONE word?
     *
     * Standalone is the whole point: `us` lives inside «campus», «because» and «discuss», and «я»
     * lives inside almost every Russian verb. A substring search here would flag or clear nearly
     * everything, which is a detector that says nothing.
     *
     * @param  list<string>  $words
     */
    private function containsAny(string $haystack, array $words): bool
    {
        foreach ($words as $word) {
            // \p{L} rather than \b: the subject side is Cyrillic, where PCRE's word boundary is only
            // right with the /u flag AND the unicode property class — «нас» must not match «насос».
            if (preg_match('/(?<![\p{L}\p{N}])' . preg_quote($word, '/') . '(?![\p{L}\p{N}])/iu', $haystack) === 1) {
                return true;
            }
        }

        return false;
    }
}
