<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Language;

use App\Modules\Plan\Domain\Exception\LanguagePackKeyMissing;
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
 */
final readonly class LanguagePack
{
    /** @var array<string, mixed> */
    private array $data;

    /** @var array<string, array<string, true>> every list of words, lower-cased, as a set */
    private array $sets;

    /** @param array<string, mixed> $data */
    public function __construct(public string $code, array $data)
    {
        $this->data = $data;
        $sets = [];
        foreach ($data as $key => $value) {
            if (is_array($value) && array_is_list($value)) {
                $sets[$key] = array_fill_keys(array_map(self::normal(...), array_filter($value, is_string(...))), true);
            }
        }
        $this->sets = $sets;
    }

    /** A language nobody has written a pack for. */
    public static function none(string $code): self
    {
        return new self($code, []);
    }

    /**
     * WHAT A COMPARISON OF SPEECH MAY READ OF THIS LANGUAGE (наряд FIX-2, п. 2) — the four lists
     * {@see \App\Modules\Shared\Domain\Service\SpeechMatch} works from, handed DOWN to the kernel because the kernel
     * cannot read this module and must not hard-code English. A key this language has not written is an empty list:
     * the rule still runs and forgives nothing, which is the right answer for a language nobody has described.
     *
     * The same lists go out to the phone ({@see SpeechPack::toArray()}), so the mirror on the device is the pack
     * itself rather than a copy of it in Dart.
     */
    public function speech(): SpeechPack
    {
        $numbers = [];
        foreach ($this->has('number_words') ? $this->map('number_words') : [] as $word => $digits) {
            if (is_string($digits)) {
                $numbers[self::normal((string) $word)] = $digits;
            }
        }

        return new SpeechPack(
            unstressed: $this->has('unstressed_words') ? $this->words('unstressed_words') : [],
            articles: $this->has('articles') ? $this->words('articles') : [],
            abbreviations: $this->has('abbreviations') ? $this->rawList('abbreviations') : [],
            numberWords: $numbers,
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

    public static function normal(string $word): string
    {
        return str_replace('’', "'", mb_strtolower(trim($word)));
    }

    private function need(string $key): void
    {
        if (! $this->has($key)) {
            throw LanguagePackKeyMissing::of($this->code, $key);
        }
    }
}
