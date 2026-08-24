<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Service;

/**
 * Script-level language purity: is a field written in the language it claims to be?
 *
 * This catches HALF the problem, and it is important to know which half. A character check finds
 * Cyrillic that leaked into an English sentence, and it finds Ukrainian letters that only exist in
 * Ukrainian (і ї є ґ) — and, mirrored, Russian letters that only exist in Russian (ы э ё ъ) sitting
 * in a Ukrainian field. It CANNOT find Ukrainian words spelled entirely in letters Russian also has
 * — «здаватися», «треба», «потрібно» — which is precisely the class the original one-off scan was
 * chasing. Those need a reader who knows both languages, which is why the enrichment model is asked
 * for lexis notes as well and both streams end up in the same findings table.
 *
 * It also cannot see a translation that is honestly labelled as another language — a `uk` row next
 * to a `ru` one is not impure, it is simply the wrong row to show. That is a query's job, not this
 * class's (see docs/ua-audit.md, class A).
 *
 * Lives in Shared because it has two unrelated consumers and no business asking either module for
 * permission: the enrichment станок, which REPORTS what it finds after the fact, and the generation
 * pipeline, which REFUSES to write what it finds. One detector, two verdicts — if they ever
 * disagreed about what "Ukrainian" means, the barrier and the audit would be describing different
 * databases.
 *
 * Pure and cheap on purpose: it runs over every field of every term on every run.
 */
final class LanguagePurity
{
    /** Letters that exist in Ukrainian and not in Russian. Their presence in a RU field is decisive. */
    private const UA_ONLY_LETTERS = ['і', 'ї', 'є', 'ґ', 'І', 'Ї', 'Є', 'Ґ'];

    /**
     * The mirror: letters that exist in Russian and not in Ukrainian. Their presence in a UK field
     * is just as decisive, and for exactly the same reason (DECISIONS п. 91 — «суржик ловится в обе
     * стороны»).
     *
     * Only one direction was implemented, which is a strange thing for a symmetric problem: the
     * content that existed was Russian, so Ukrainian leaking INTO it was the failure anyone had
     * seen. Ukrainian is a support language in v1 (п. 85), so the other direction is now content
     * somebody will actually read, and a check that watches one door is a check that says «clean»
     * about a room with two.
     *
     * `ё` belongs here with the rest: Ukrainian does not have it, and it is not a Russian
     * typographic option the way `е`/`ё` is within Russian — inside a Ukrainian field it is a
     * Russian letter and nothing else.
     */
    private const RU_ONLY_LETTERS = ['ы', 'э', 'ё', 'ъ', 'Ы', 'Э', 'Ё', 'Ъ'];

    /**
     * Letters that betray a foreign language in a field DECLARED to be `$lang`. Empty = clean.
     *
     * Three languages have a rule here, and that is deliberate rather than unfinished: we know what
     * a Russian field must not contain (its close relative's letters), what a Ukrainian one must not
     * (the mirror of the same), and what an English one must not (anything non-Latin). For any other
     * language an honest "no opinion" beats a guess — a caller that treats silence as a pass keeps
     * writing, which is the correct default for a check that exists to catch one specific, known
     * failure.
     *
     * @param  string  $lang  ISO code the value claims to be written in
     * @return list<string>  the offending characters, deduped, in order
     */
    public function foreignLetters(string $lang, string $value): array
    {
        return match (strtolower(trim($lang))) {
            'ru' => $this->ukrainianLetters($value),
            'uk' => $this->russianLetters($value),
            'en' => $this->nonEnglishLetters($value),
            default => [],
        };
    }

    /**
     * Is this value clean for `$lang` — by letters AND by script?
     *
     * The two checks catch different failures and neither implies the other: «на одній хвилі» is
     * Cyrillic through and through and still not Russian, while «Kann ich mit Karte bezahlen?»
     * contains no Ukrainian letter at all and is not Russian either.
     */
    public function isClean(string $lang, string $value): bool
    {
        return $this->foreignLetters($lang, $value) === [] && ! $this->isWrongScript($lang, $value);
    }

    /**
     * MOST of the letters belong to a script the declared language is not written in.
     *
     * Found live, after the letter check had already been applied: a German example translation
     * («Kann ich mit Karte bezahlen, oder nur bar?») sitting in a Russian field, invisible to
     * {@see foreignLetters()} because German shares no letter with Ukrainian. Whole-field wrong
     * language is the coarser and more obvious failure, and nothing was looking for it.
     *
     * A MAJORITY, not "any", and that threshold is the whole design. Russian legitimately carries
     * Latin fragments — «пароль от Wi-Fi», «сеть Wi-Fi» — and a rule that flagged any Latin letter
     * would reject correct content, which is worse than missing something: a barrier that cries
     * wolf gets switched off. On the live data the separation is not close (33 Latin vs 0 Cyrillic
     * for the German row; 4 vs 13 for the Wi-Fi ones), so a tie counts as clean.
     */
    public function isWrongScript(string $lang, string $value): bool
    {
        $expected = match (strtolower(trim($lang))) {
            'ru', 'uk' => '/\p{Cyrillic}/u',
            'en' => '/\p{Latin}/u',
            default => null,
        };
        if ($expected === null) {
            return false;
        }

        if (preg_match_all('/\p{L}/u', $value, $m) === false || $m[0] === []) {
            return false;
        }

        $right = 0;
        foreach ($m[0] as $char) {
            if (preg_match($expected, $char) === 1) {
                $right++;
            }
        }

        return $right * 2 < count($m[0]);
    }

    /**
     * Non-Latin letters in a field that is supposed to be English. Returns the offending
     * characters (deduped, in order) or an empty list.
     *
     * @return list<string>
     */
    public function nonEnglishLetters(string $value): array
    {
        return $this->matchAll('/[\p{L}]/u', $value, static fn (string $ch): bool => preg_match('/[\p{Latin}]/u', $ch) !== 1);
    }

    /**
     * Ukrainian-only letters in a field that is supposed to be Russian.
     *
     * @return list<string>
     */
    public function ukrainianLetters(string $value): array
    {
        return $this->matchAll('/[\p{L}]/u', $value, static fn (string $ch): bool => in_array($ch, self::UA_ONLY_LETTERS, true));
    }

    /**
     * Russian-only letters in a field that is supposed to be Ukrainian — the mirror of
     * {@see ukrainianLetters()}, see {@see RU_ONLY_LETTERS}.
     *
     * It catches the same HALF of the problem in the other direction, and the same half is missed:
     * a Russian word spelled entirely in letters Ukrainian also has («сейчас», «работа») goes
     * through untouched. That limit is the class's, not this method's — see the class docblock.
     *
     * @return list<string>
     */
    public function russianLetters(string $value): array
    {
        return $this->matchAll('/[\p{L}]/u', $value, static fn (string $ch): bool => in_array($ch, self::RU_ONLY_LETTERS, true));
    }

    /**
     * @param  callable(string): bool  $offends
     * @return list<string>
     */
    private function matchAll(string $pattern, string $value, callable $offends): array
    {
        if (preg_match_all($pattern, $value, $m) === false) {
            return [];
        }

        $found = [];
        foreach ($m[0] as $char) {
            if ($offends($char) && ! in_array($char, $found, true)) {
                $found[] = $char;
            }
        }

        return $found;
    }
}
