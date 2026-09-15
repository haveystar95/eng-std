<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Language;

use App\Modules\Plan\Domain\Exception\LanguagePackKeyMissing;

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
