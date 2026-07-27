<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Domain\Entity;

use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Vocabulary\Domain\ValueObject\PartOfSpeech;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Domain\ValueObject\TermSource;
use App\Modules\Vocabulary\Domain\ValueObject\TermText;
use App\Modules\Vocabulary\Domain\ValueObject\TermType;
use App\Modules\Vocabulary\Domain\ValueObject\Translation;
use DateTimeImmutable;

/**
 * A canonical dictionary entry — one row per (lang, normalized_text, pos).
 * Aggregate root for its translations.
 */
final class Term
{
    /** @var list<Translation> */
    private array $translations;

    /** @param list<Translation> $translations */
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
    ) {
        $this->translations = [];
        foreach ($translations as $translation) {
            $this->addTranslation($translation);
        }
    }

    /** @param list<Translation> $translations */
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
    ): self {
        return new self($id, $lang, $text, $normalizedText, $type, $pos, $source, $createdAt, $translations);
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
}
