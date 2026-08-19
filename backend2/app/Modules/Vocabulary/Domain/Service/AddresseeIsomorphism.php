<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Domain\Service;

use App\Modules\Vocabulary\Domain\ValueObject\AddresseeDirection;
use App\Modules\Vocabulary\Domain\ValueObject\AddresseeGap;

/**
 * A COARSE detector for translations that no longer point at their own source (QA-17, QA-22).
 *
 * The class of defect: a translation is not prose, it is the KEY the learner is asked to turn back
 * into the source. «Tell us about a challenge you faced» came back as «Расскажите о вызове, с
 * которым вы столкнулись» — fluent, and unanswerable, because «нам» is gone and `Tell me…`,
 * `Tell us…` and `Describe…` all fit it equally well. An honest answer then goes into an append-only
 * log as a lapse. The words that go missing are almost always the small ones that say WHO is
 * speaking and WHO is addressed, because a translation reads more smoothly without them.
 *
 * Both directions break a key, so both are judged:
 *
 * - LOST — the source addresses someone, the translation carries nothing of that person.
 * - EXTRA — the mirror (QA-22): the translation says something the source never did. «I get along
 *   with my team» came back as «Я **хорошо** лажу со своей командой», and a learner who answers with
 *   the source is marked wrong for missing a `well` that was never there.
 *
 * The rule is per GROUP rather than against one flat list of words: a source that says `us` is not
 * rescued by a translation that happens to contain «вы». Each group carries three lists —
 *
 * - `triggers`  — source words that DEMAND the group in the translation (drives LOST);
 * - `licences`  — source words that ALLOW it (drives EXTRA);
 * - `counterparts` — per learner language, the forms that carry the group.
 *
 * Triggers and licences are deliberately different sets. `me` demands a first-person form; `I` and
 * `my` only permit one — Russian and Ukrainian drop the subject pronoun constantly, so demanding
 * «я» for every `I` would flag half the store, while a translation that says «я» for a source with
 * `I` in it is obviously fine. Merging the two lists would make one of the directions useless.
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
     * The groups, as (name, English triggers, English licences, counterparts per learner language).
     *
     * `you` sits with `your`/`yours` because both Russian and Ukrainian mark them with the same
     * stem, and separating them would flag «расскажите нам о вашем опыте» for having no separate
     * word for `you`. For the same reason each group is a PERSON, not a case: any form of the second
     * person answers `you`, and «нам» — first person plural, dative — answers `we` as readily as it
     * answers `us` («Could we have the bill?» → «Можно **нам** счёт?»). A group's counterpart list
     * is therefore that person's whole paradigm, and two groups may share a word where the person
     * they mark overlaps.
     *
     * The ты-forms sit in `you/your` because Russian's informal second person is still the second
     * person: «На **твоём** месте я бы согласился» answers `If I were you` completely, and the rule
     * used to flag it only because it had been taught вы-forms and not ты-forms. Ukrainian is kept
     * symmetric with it for the same reason — the same defect must not be a hit in one language and
     * clean in the other.
     *
     * `well/хорошо` is not a person at all, and it is here because it breaks a key the same way: the
     * EXTRA direction is where it earns its place.
     *
     * @var list<array{name: string, triggers: list<string>, licences: list<string>, counterparts: array<string, list<string>>}>
     */
    private const GROUPS = [
        [
            'name' => 'us/me',
            'triggers' => ['us', 'me'],
            // First person, in any shape: a translation may legitimately say «я»/«мне» whenever the
            // source is in the first person at all, subject pronoun included.
            'licences' => ['us', 'me', 'i', 'my', 'mine', 'myself', 'we', 'our', 'ours', 'ourselves', "let's", 'lets'],
            'counterparts' => [
                'ru' => ['нам', 'нас', 'нами', 'мне', 'меня', 'мной', 'мною', 'я'],
                'uk' => ['нам', 'нас', 'нами', 'мені', 'мене', 'мною', 'я'],
            ],
        ],
        [
            'name' => 'you/your',
            'triggers' => ['you', 'your', 'yours'],
            'licences' => ['you', 'your', 'yours', 'yourself', 'yourselves'],
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
            'licences' => ['we', 'our', 'ours', 'us', 'ourselves', "let's", 'lets'],
            'counterparts' => [
                'ru' => ['мы', 'нам', 'нас', 'нами', 'наш', 'наша', 'наше', 'наши', 'нашего', 'нашему', 'нашей', 'нашем', 'нашим', 'нашими', 'наших', 'нашу'],
                'uk' => ['ми', 'нам', 'нас', 'нами', 'наш', 'наша', 'наше', 'наші', 'нашого', 'нашому', 'нашій', 'нашим', 'нашими', 'наших', 'нашу'],
            ],
        ],
        [
            'name' => 'well/хорошо',
            'triggers' => ['well'],
            // Anything in the source that can honestly come out as «хорошо». Wide on purpose: this
            // group exists for the EXTRA direction, where the question is «did ANYTHING license
            // this», and a narrow list would report every idiomatic «хорошо» as invented.
            'licences' => ['well', 'good', 'fine', 'nice', 'nicely', 'great', 'okay', 'ok', 'alright', 'right', 'better', 'best'],
            'counterparts' => [
                'ru' => ['хорошо'],
                'uk' => ['добре'],
            ],
        ],
    ];

    /**
     * Everything wrong with this pair, LOST first — the rule's whole answer.
     *
     * @param  string  $source  the side the learner must reproduce: a term, or an example sentence
     * @param  string  $lang  the translation's language — which counterpart list applies. A group
     *                        with no list for this language is skipped entirely (never a false hit
     *                        for a learner language the rule hasn't been taught yet).
     * @return list<AddresseeGap>
     */
    public function gaps(string $source, string $translation, string $lang = 'ru'): array
    {
        return array_merge(
            $this->misses($source, $translation, $lang),
            $this->extras($source, $translation, $lang),
        );
    }

    /**
     * LOST: per tripped group, the source's own words that went unanswered.
     *
     * @return list<AddresseeGap>  in the order the groups are declared above
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
                continue; // the source never demands this group
            }
            if ($this->containsAny($translation, $counterparts)) {
                continue; // …and the translation carries it
            }
            $out[] = new AddresseeGap(AddresseeDirection::Lost, $group['name'], $used, $counterparts);
        }

        return $out;
    }

    /**
     * EXTRA: per group, the translation's words that nothing in the source licenses.
     *
     * The mirror of {@see misses()} and not its inverse: a group fires here when the translation
     * carries one of its forms and the source has NONE of the group's licences — not merely none of
     * its triggers. `I get along with my team` licenses «я» (first person, `I`/`my`) and licenses no
     * «хорошо» at all, which is exactly the row this direction was built to find.
     *
     * @return list<AddresseeGap>  in the order the groups are declared above
     */
    public function extras(string $source, string $translation, string $lang = 'ru'): array
    {
        $out = [];
        $alreadyReported = [];
        foreach (self::GROUPS as $group) {
            $counterparts = $group['counterparts'][$lang] ?? null;
            if ($counterparts === null) {
                continue;
            }
            $carried = $this->matched($translation, $counterparts);
            if ($carried === []) {
                continue; // the translation says nothing of this group
            }
            if ($this->containsAny($source, $group['licences'])) {
                continue; // …and the source allows it
            }
            // Groups overlap where the person they mark overlaps — «нам» is in both `us/me` and
            // `we/our` — and one unlicensed «нам» is ONE thing to read, not two rows saying it twice.
            // Only the words no earlier group has already reported make this a row.
            $fresh = array_values(array_diff($carried, $alreadyReported));
            if ($fresh === []) {
                continue;
            }
            $alreadyReported = array_merge($alreadyReported, $fresh);
            $out[] = new AddresseeGap(AddresseeDirection::Extra, $group['name'], $fresh, $group['licences']);
        }

        return $out;
    }

    /**
     * The groups this pair loses — empty when the translation carries everything the source demands.
     *
     * @param  string  $lang  the translation's language, as in {@see misses()}
     * @return list<string>  group names, in the order declared above
     */
    public function violations(string $source, string $translation, string $lang = 'ru'): array
    {
        return array_map(
            static fn (AddresseeGap $gap): string => $gap->group,
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
