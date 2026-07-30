<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Domain\Entity;

use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Vocabulary\Domain\ValueObject\Example;
use App\Modules\Vocabulary\Domain\ValueObject\PartOfSpeech;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Domain\ValueObject\TermSource;
use App\Modules\Vocabulary\Domain\ValueObject\TermText;
use App\Modules\Vocabulary\Domain\ValueObject\TermType;
use App\Modules\Vocabulary\Domain\ValueObject\Translation;
use DateTimeImmutable;

/**
 * A canonical dictionary entry — one row per (lang, normalized_text, pos).
 * Aggregate root for its translations, pronunciation (IPA) and usage examples.
 */
final class Term
{
    /** @var list<Translation> */
    private array $translations;

    /** @var list<Example> */
    private array $examples;

    private ?string $ipa;

    private ?string $cefr;

    /**
     * @param list<Translation> $translations
     * @param list<Example> $examples
     */
    private function __construct(
        private readonly TermId $id,
        private readonly LanguageCode $lang,
        private readonly TermText $text,
        private readonly string $normalizedText,
        private readonly TermType $type,
        private readonly ?PartOfSpeech $pos,
        private readonly TermSource $source,
        private readonly DateTimeImmutable $createdAt,
        array $translations,
        ?string $ipa,
        array $examples,
        ?string $cefr,
    ) {
        $this->translations = [];
        foreach ($translations as $translation) {
            $this->addTranslation($translation);
        }
        $this->ipa = $this->cleanIpa($ipa);
        $this->cefr = $this->cleanCefr($cefr);
        $this->examples = [];
        foreach ($examples as $example) {
            $this->addExample($example);
        }
    }

    /**
     * @param list<Translation> $translations
     * @param list<Example> $examples
     */
    public static function create(
        TermId $id,
        LanguageCode $lang,
        TermText $text,
        string $normalizedText,
        TermType $type,
        ?PartOfSpeech $pos,
        TermSource $source,
        DateTimeImmutable $createdAt,
        array $translations = [],
        ?string $ipa = null,
        array $examples = [],
        ?string $cefr = null,
    ): self {
        return new self($id, $lang, $text, $normalizedText, $type, $pos, $source, $createdAt, $translations, $ipa, $examples, $cefr);
    }

    /** Add a translation, ignoring exact (lang,text) duplicates. */
    public function addTranslation(Translation $translation): void
    {
        foreach ($this->translations as $existing) {
            if ($existing->lang->equals($translation->lang) && $existing->text === $translation->text) {
                return;
            }
        }
        $this->translations[] = $translation;
    }

    /** Add a usage example, ignoring duplicates by sentence (case-insensitive). */
    public function addExample(Example $example): void
    {
        $key = mb_strtolower($example->sentence);
        foreach ($this->examples as $existing) {
            if (mb_strtolower($existing->sentence) === $key) {
                return;
            }
        }
        $this->examples[] = $example;
    }

    /** Fill in the pronunciation only when the term doesn't have one yet (dedup-merge safe). */
    public function ensureIpa(?string $ipa): void
    {
        $clean = $this->cleanIpa($ipa);
        if ($this->ipa === null && $clean !== null) {
            $this->ipa = $clean;
        }
    }

    /** Fill in the CEFR level only when the term doesn't have one yet (dedup-merge safe). */
    public function ensureCefr(?string $cefr): void
    {
        $clean = $this->cleanCefr($cefr);
        if ($this->cefr === null && $clean !== null) {
            $this->cefr = $clean;
        }
    }

    private function cleanIpa(?string $ipa): ?string
    {
        if ($ipa === null) {
            return null;
        }
        $trimmed = trim($ipa);

        return $trimmed !== '' ? $trimmed : null;
    }

    /** Uppercase and validate; anything not one of A1..C2 becomes null ("unknown"). */
    private function cleanCefr(?string $cefr): ?string
    {
        if ($cefr === null) {
            return null;
        }
        $upper = strtoupper(trim($cefr));

        return in_array($upper, ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'], true) ? $upper : null;
    }

    public function id(): TermId
    {
        return $this->id;
    }

    public function lang(): LanguageCode
    {
        return $this->lang;
    }

    public function text(): TermText
    {
        return $this->text;
    }

    public function normalizedText(): string
    {
        return $this->normalizedText;
    }

    public function type(): TermType
    {
        return $this->type;
    }

    public function pos(): ?PartOfSpeech
    {
        return $this->pos;
    }

    public function source(): TermSource
    {
        return $this->source;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return list<Translation> */
    public function translations(): array
    {
        return $this->translations;
    }

    public function ipa(): ?string
    {
        return $this->ipa;
    }

    /** CEFR level (A1..C2), or null when unknown — read neutrally, never as a risk. */
    public function cefr(): ?string
    {
        return $this->cefr;
    }

    /** @return list<Example> */
    public function examples(): array
    {
        return $this->examples;
    }
}
