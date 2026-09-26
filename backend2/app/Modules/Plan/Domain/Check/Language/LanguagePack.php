<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Language;

use App\Modules\Plan\Domain\Exception\LanguagePackKeyMissing;
use App\Modules\Shared\Domain\Service\LexicalNormalizer;
use App\Modules\Shared\Domain\Service\TextNormalizer;
use App\Modules\Shared\Domain\ValueObject\SpeechPack;

/**
 * WHAT THE LESSON VALIDATOR KNOWS OF ONE LANGUAGE (наряд GEN-2b, `docs/plan-v2.md` §4) — its words, marks and
 * patterns, read from `config/lesson/lang/<code>.php`. A rule is about the PAIR of languages and asks the pack of
 * the side it reads: the target's for what the learner says and hears, the learner's own for readings, native
 * frames and listening.
 *
 * A key that holds null — or a language with no pack at all — is a key nobody has written for that language: the
 * rule that needs it does not run and says so ({@see \App\Modules\Plan\Domain\Check\LessonValidationContext::reads()}),
 * it never guesses and never borrows another language's words. Asking for such a key without asking first is a
 * bug, and throws.
 *
 * THE PACK KNOWS ITS NEIGHBOURS (наряд LANG-1 §5). With seven languages taught and nine spoken, a line may be in the
 * wrong language and still in the right letters — a Polish learner's grey line is in Latin letters, and so is the
 * English it should have translated. A rule that has only the learner's pack in hand ({@see \App\Modules\Plan\Domain\Service\ReplyNative},
 * called where the target's pack cannot be passed) tells the learner's language APART from the others by what
 * {@see LanguagePacks} hands every pack it gives out: each OTHER pack's letters and most frequent words
 * ({@see neighbours()}). That is no borrowing: another language's words never check this language's text as if they
 * were its own — they only say which language a line is not.
 */
final readonly class LanguagePack
{
    /** @var array<string, mixed> */
    private array $data;

    /** @var array<string, array<string, true>> every list of words, lower-cased, as a set */
    private array $sets;

    /** @var array<string, array{script_letters: ?string, common_words: list<string>}> the other packs, by code */
    private array $neighbours;

    /**
     * `$neighbours` — what the other packs of the deployment are ({@see asNeighbour()}), by code: given by
     * {@see LanguagePacks}; a pack built alone knows none, and its own code among them is dropped.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, array{script_letters: ?string, common_words: list<string>}>  $neighbours
     */
    public function __construct(public string $code, array $data, array $neighbours = [])
    {
        $this->data = $data;
        $sets = [];
        foreach ($data as $key => $value) {
            if (is_array($value) && array_is_list($value)) {
                $sets[$key] = array_fill_keys(array_map(self::normal(...), array_filter($value, is_string(...))), true);
            }
        }
        $this->sets = $sets;
        unset($neighbours[$code]);
        $this->neighbours = $neighbours;
    }

    /**
     * A language nobody has written a pack for — it may still be told what the others are.
     *
     * @param  array<string, array{script_letters: ?string, common_words: list<string>}>  $neighbours
     */
    public static function none(string $code, array $neighbours = []): self
    {
        return new self($code, [], $neighbours);
    }

    /**
     * THE LANGUAGE'S MOST FREQUENT WORDS (наряд LANG-1 §5, key `common_words`) — some thirty of them, lower-cased as
     * {@see normal()} gives them: what tells a line of this language from a line of another in the same letters
     * ({@see \App\Modules\Plan\Domain\Service\ReplyNative}). A word is a run of letters — a line is split on everything
     * else, the apostrophe too, so an entry like «don't» or «il y a» could never be met. An empty list when the pack
     * does not write the key: the neighbours are then not told apart, and nothing is refused for it.
     *
     * @return list<string>
     */
    public function commonWords(): array
    {
        return $this->has('common_words') ? $this->words('common_words') : [];
    }

    /**
     * THE TITLE OF A TALK IN THIS LANGUAGE (наряд LANG-1, key `talk_title_template`) — written by the learner's side of
     * the pair for a language whose declension the code does not know (every native but ru and uk; en keeps its
     * constant): `title` holds «{roles}» where the roles go, `and` joins the last two («a, b and c»), `anyone` is the
     * title when there are no roles, `lower_first` lower-cases a role's first letter unless the role is an acronym (de
     * keeps its capitals). Null when the pack does not write it — or writes the no-op `[]`, a language whose title the
     * code builds itself (ru, uk, en); a template written wrong — a field missing, blank or of another type — is a
     * pack's bug and throws, naming the field: a title with no place for its roles would name nobody, and the title of
     * the talk ({@see \App\Modules\Plan\Domain\Service\NativeStrings::talkTitle()}) falls back to English on a blank
     * one without a word — this is where the deployment's packs are held to the shape (LanguagePacksTest). The
     * optional `and_before` (es «médico e internista») is read by the title alone and is not handed out here.
     *
     * @return array{title: string, and: string, anyone: string, lower_first: bool}|null
     */
    public function talkTitleTemplate(): ?array
    {
        if (! $this->has('talk_title_template') || $this->data['talk_title_template'] === []) {
            return null;
        }
        $template = $this->map('talk_title_template');
        $title = $template['title'] ?? null;
        if (! is_string($title) || ! str_contains($title, '{roles}')) {
            throw LanguagePackKeyMissing::of($this->code, 'talk_title_template.title');
        }
        $lowerFirst = $template['lower_first'] ?? null;
        if (! is_bool($lowerFirst)) {
            throw LanguagePackKeyMissing::of($this->code, 'talk_title_template.lower_first');
        }

        return [
            'title' => $title,
            'and' => $this->templateWord($template, 'and'),
            'anyone' => $this->templateWord($template, 'anyone'),
            'lower_first' => $lowerFirst,
        ];
    }

    /** @param array<string, mixed> $template */
    private function templateWord(array $template, string $field): string
    {
        $value = $template[$field] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : throw LanguagePackKeyMissing::of($this->code, "talk_title_template.{$field}");
    }

    /**
     * WHAT THE OTHER PACKS ARE TOLD OF THIS ONE (наряд LANG-1 §5): its letters as the pack writes them — the pattern
     * string itself, since two languages share a script exactly when their packs write the SAME `script_letters` — and
     * its {@see commonWords()}. Letters the pack does not write are null: such a language is nobody's neighbour.
     *
     * @return array{script_letters: ?string, common_words: list<string>}
     */
    public function asNeighbour(): array
    {
        $letters = $this->data['script_letters'] ?? null;

        return ['script_letters' => is_string($letters) ? $letters : null, 'common_words' => $this->commonWords()];
    }

    /**
     * Every OTHER pack of the deployment as {@see asNeighbour()} describes it, by code — never this one. Empty for a
     * pack built alone rather than handed out by {@see LanguagePacks}.
     *
     * @return array<string, array{script_letters: ?string, common_words: list<string>}>
     */
    public function neighbours(): array
    {
        return $this->neighbours;
    }

    /**
     * WHAT A COMPARISON OF SPEECH MAY READ OF THIS LANGUAGE (наряд FIX-2, п. 2) — the lists
     * {@see \App\Modules\Shared\Domain\Service\SpeechMatch} works from, handed DOWN to the kernel because the kernel
     * cannot read this module and must not hard-code English. A key this language has not written is an empty list:
     * the rule still runs and forgives nothing, which is the right answer for a language nobody has described.
     *
     * The same lists go out to the phone ({@see SpeechPack::toArray()}), so the mirror on the device is the pack
     * itself rather than a copy of it in Dart.
     *
     * IN THE FORM OF THE TEXT THEY MEET (наряд LANG-1 §4). A list is only as good as the spelling it is compared in: the
     * words of a text reach these lists as {@see \App\Modules\Shared\Domain\Service\SpeechMatch::words()} gives them —
     * the kernel's canonical form ({@see LexicalNormalizer::canonicalize()}: folded — NFC, «ß» as «ss», «œ» as «oe», the
     * Romanian cedilla letters with the comma below —, lower case, no apostrophe, every other mark and the hyphen a
     * space). So every WORD the pack hands down — the entries of `number_words`, `articles`, `number_joiners`,
     * `number_tens_joiners`, `unstressed_words` — is put in that very form here, by the very same function, and a pack may
     * write a word the way the language writes it: de «dreißig» is the `dreissig` a text says, fr «quatre-vingt-dix» the
     * `quatre vingt dix` of three words, ro «şi» with a cedilla the `și` of the text. An entry that folds into nothing is
     * dropped; two that fold into one are one, and the first written wins (its value, its place in the list).
     *
     * `abbreviations` are the exception, because a text meets them BEFORE it is folded: they are searched for in the text
     * as written, letter case aside, and their dots are their whole content — so they only take the form text is
     * stored in ({@see TextNormalizer::canonical()}: composed, the comma below), with their dots and their case.
     *
     * English and Russian are written in that form already, and hand down exactly the lists they did before LANG-1.
     */
    public function speech(): SpeechPack
    {
        $lexical = new LexicalNormalizer;
        // A list of words in the text's form: canonical, nothing empty, each once — the first written wins.
        $words = function (string $key) use ($lexical): array {
            $out = [];
            foreach ($this->has($key) ? $this->words($key) : [] as $word) {
                $canonical = $lexical->canonicalize($word);
                if ($canonical !== '') {
                    $out[$canonical] = true;
                }
            }

            return array_map('strval', array_keys($out));
        };

        $numbers = [];
        foreach ($this->has('number_words') ? $this->map('number_words') : [] as $entry => $digits) {
            $canonical = $lexical->canonicalize((string) $entry);
            if (is_string($digits) && $canonical !== '' && ! isset($numbers[$canonical])) {
                $numbers[$canonical] = $digits;
            }
        }
        $unicode = new TextNormalizer;

        return new SpeechPack(
            unstressed: $words('unstressed_words'),
            articles: $words('articles'),
            abbreviations: $this->has('abbreviations') ? array_map($unicode->canonical(...), $this->rawList('abbreviations')) : [],
            numberWords: $numbers,
            numberJoiners: $words('number_joiners'),
            numberTensJoiners: $words('number_tens_joiners'),
        );
    }

    /**
     * A list AS IT IS WRITTEN — the only reader is `abbreviations`, whose whole content is where the dots stand
     * («p.m.», «т. е.»); {@see words()} lower-cases and would not lose them, but the set it builds is keyed by the
     * normal form and the spelling is what a text is searched for.
     *
     * @return list<string>
     */
    private function rawList(string $key): array
    {
        $value = $this->data[$key] ?? null;

        return is_array($value) ? array_values(array_filter($value, is_string(...))) : [];
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /** Where a sentence of this language ends ({@see SentenceEnds}) — null for a language whose pack does not say. */
    public function sentenceEnds(): ?SentenceEnds
    {
        return $this->has('sentence_ends') ? new SentenceEnds($this) : null;
    }

    /**
     * «НЕ ПОНЯЛ» IN THIS LANGUAGE (наряд CONV-2, п. 4а) — what a rescue move of the talk says in the learner's own
     * bubble: en «Sorry?». Null for a language nobody has written it for — the move then carries no words, as before.
     */
    public function rescueLine(): ?string
    {
        $value = $this->data['rescue_line'] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * THE RESCUE KIT OF THIS LANGUAGE AS A TARGET (наряд LANG-1b §2, key `rescue`): the six lines a learner of it says when
     * stuck in a real talk — «Sorry?», «Could you say that more slowly, please?», «I don't understand.», «One moment.», «Can
     * you write it down?», «Thank you.» — each in this language (`target`) with its translation into every learner's
     * language of the plan (`native`: ru, uk, be, pl, ro, es, it, de, fr). The kit of a plan is its target's, translated
     * into its learner's language ({@see \App\Modules\Plan\Application\Service\RescueKits}). A language that is only ever
     * a learner's writes the no-op `[]`; a row written wrong — a text missing or blank — is a pack's bug and throws, naming
     * the row (LanguagePacksTest holds the deployment's packs to the shape).
     *
     * @return list<array{target: string, native: array<string, string>}>
     */
    public function rescue(): array
    {
        $rows = $this->data['rescue'] ?? [];
        if (! is_array($rows)) {
            throw LanguagePackKeyMissing::of($this->code, 'rescue');
        }
        $out = [];
        foreach (array_values($rows) as $index => $row) {
            $target = is_array($row) ? ($row['target'] ?? null) : null;
            $natives = is_array($row) ? ($row['native'] ?? null) : null;
            if (! is_string($target) || trim($target) === '' || ! is_array($natives) || $natives === []) {
                throw LanguagePackKeyMissing::of($this->code, "rescue.{$index}");
            }
            $native = [];
            foreach ($natives as $code => $text) {
                if (! is_string($text) || trim($text) === '') {
                    throw LanguagePackKeyMissing::of($this->code, "rescue.{$index}.native.{$code}");
                }
                $native[(string) $code] = $text;
            }
            $out[] = ['target' => $target, 'native' => $native];
        }

        return $out;
    }

    /**
     * THE ROLE'S NEUTRAL MOVE IN THIS LANGUAGE (наряд BACK-TAILS-2 §9) — «I see. Please go on.»: what the role says when
     * its answer was nothing but the learner's words said back. Every pack writes the same line, so the target's is said
     * and the learner's is its translation. Null for a language nobody has written it for.
     */
    public function neutralReply(): ?string
    {
        $value = $this->data['neutral_reply'] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** Is the word one of the list under `$key` (lower-cased, a typographic apostrophe read as a plain one)? */
    public function listed(string $key, string $word): bool
    {
        $this->need($key);

        return isset($this->sets[$key][self::normal($word)]);
    }

    /** @return list<string> the list under `$key`, lower-cased */
    public function words(string $key): array
    {
        $this->need($key);

        return array_map('strval', array_keys($this->sets[$key] ?? []));
    }

    public function pattern(string $key): string
    {
        $this->need($key);
        $value = $this->data[$key];

        return is_string($value) ? $value : throw LanguagePackKeyMissing::of($this->code, $key);
    }

    /** @return array<string, mixed> */
    public function map(string $key): array
    {
        $this->need($key);
        $value = $this->data[$key];
        if (! is_array($value)) {
            throw LanguagePackKeyMissing::of($this->code, $key);
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /** @return list<string> a list inside a map (`clause.subjects`), lower-cased */
    public function mapWords(string $key, string $field): array
    {
        $value = $this->map($key)[$field] ?? [];

        return is_array($value) ? array_values(array_map(self::normal(...), array_filter($value, is_string(...)))) : [];
    }

    public function mapString(string $key, string $field): string
    {
        $value = $this->map($key)[$field] ?? null;

        return is_string($value) ? $value : throw LanguagePackKeyMissing::of($this->code, "{$key}.{$field}");
    }

    public function mapInt(string $key, string $field): int
    {
        $value = $this->map($key)[$field] ?? null;

        return is_int($value) ? $value : throw LanguagePackKeyMissing::of($this->code, "{$key}.{$field}");
    }

    /**
     * A WORD AS EVERY LIST OF THE PACK IS KEPT AND ASKED — folded ({@see TextNormalizer::fold()}: composed, «ß» as «ss»,
     * «œ» as «oe», the Romanian cedilla letters with the comma below), lower case, the typographic apostrophe a plain one.
     *
     * The fold is here since наряд LANG-1 §4, on both sides at once: the pack's lists are built through this function
     * (every list as a set, {@see words()}, {@see mapWords()}) and so is every word a rule asks of them ({@see listed()},
     * and the rules that fold their own tokens with it before comparing — {@see LanguageWords}, {@see
     * \App\Modules\Plan\Domain\Service\ReplyNative}, {@see \App\Modules\Plan\Domain\Assembly\WordCards}). Without it a
     * model's «ş» with a cedilla — what models and keyboards write most ({@see TextNormalizer}) — never met the «ș» of a
     * Romanian list, and a de list writing «heißen» never met a line that writes «heissen». Every English and Russian
     * word is the same with it and without it (LanguagePackTest walks both packs).
     *
     * A regular expression of the pack that reads a word in THIS form (`number_pattern`, `time_pattern`,
     * `amount_pattern`, `amount_prefix`) meets «ß» as «ss» and «ş» as «ș»: it names such a letter in both spellings,
     * `(?:ß|ss)`, `[șş]`, as the key spec asks (`docs/research/lang-1/pack-keys.md` §1.3) — a pattern that writes «ß»
     * alone no longer matches.
     */
    public static function normal(string $word): string
    {
        return str_replace('’', "'", mb_strtolower((new TextNormalizer)->fold(trim($word))));
    }

    private function need(string $key): void
    {
        if (! $this->has($key)) {
            throw LanguagePackKeyMissing::of($this->code, $key);
        }
    }
}
