<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Domain\Entity;

use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Vocabulary\Domain\ValueObject\Example;
use App\Modules\Vocabulary\Domain\ValueObject\PartOfSpeech;
use App\Modules\Vocabulary\Domain\ValueObject\Provenance;
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

    private ?string $imageUrl;

    private ?string $imageApiPrompt;

    private ?string $imageAuthor;

    private ?string $imageAuthorUrl;

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
        ?string $imageUrl,
        ?string $imageApiPrompt,
        ?string $imageAuthor,
        ?string $imageAuthorUrl,
        private readonly ?Provenance $provenance,
    ) {
        $this->translations = [];
        foreach ($translations as $translation) {
            $this->addTranslation($translation);
        }
        $this->ipa = $this->cleanIpa($ipa);
        $this->cefr = $this->cleanCefr($cefr);
        $this->imageUrl = $this->clean($imageUrl);
        $this->imageApiPrompt = $this->clean($imageApiPrompt);
        $this->imageAuthor = $this->clean($imageAuthor);
        $this->imageAuthorUrl = $this->clean($imageAuthorUrl);
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
        ?string $imageUrl = null,
        ?string $imageApiPrompt = null,
        ?string $imageAuthor = null,
        ?string $imageAuthorUrl = null,
        ?Provenance $provenance = null,
    ): self {
        return new self(
            $id, $lang, $text, $normalizedText, $type, $pos, $source, $createdAt, $translations, $ipa, $examples, $cefr,
            $imageUrl, $imageApiPrompt, $imageAuthor, $imageAuthorUrl, $provenance,
        );
    }

    /**
     * Add a translation, ignoring exact (lang,text) duplicates.
     *
     * ## A7: exactly one primary per language, and it is the newest one
     *
     * A term is global and deduplicated, so it accumulates translations: every regeneration of the
     * same text merges another reading in. Until this rule existed the merge simply appended, and a
     * translation that arrived marked primary landed BESIDE the primary already there — ten live
     * store terms ended up with two («stay calm» → «Оставайтесь спокойны» AND «оставаться
     * спокойным»). The question on the card is then whichever row the reader's ordering happens to
     * return, which is a coin flip over what the learner is asked.
     *
     * So a primary arriving for a language demotes the primary that language already had. The old
     * row is NOT deleted — it is a genuine alternative reading and it stays available — it only
     * stops being the question. Freshest wins, because a merge is the newer generation speaking and
     * a term with no opinion beats a term with two.
     *
     * An exact (lang,text) duplicate is not an arrival at all: nothing new was said, so nothing is
     * promoted or demoted. That is what keeps a re-run of the same generation a genuine no-op, and
     * it is what stops a repeat generation from taking the primary flag off a row a curator moved it
     * onto by hand.
     */
    public function addTranslation(Translation $translation): void
    {
        foreach ($this->translations as $existing) {
            if ($existing->lang->equals($translation->lang) && $existing->text === $translation->text) {
                return;
            }
        }

        if ($translation->isPrimary) {
            $this->translations = array_map(
                static fn (Translation $existing): Translation => $existing->lang->equals($translation->lang)
                    ? $existing->demoted()
                    : $existing,
                $this->translations,
            );
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

    /** Fill in the image-search query only when absent (dedup-merge safe, like ensureIpa). */
    public function ensureImageApiPrompt(?string $prompt): void
    {
        $clean = $this->clean($prompt);
        if ($this->imageApiPrompt === null && $clean !== null) {
            $this->imageApiPrompt = $clean;
        }
    }

    /**
     * Attach a found stock photo. NEVER overwrites an existing image — a term is global and
     * deduplicated, so the first collection to image it wins and every other reuses that photo.
     * A blank url is ignored. Attribution rides along; a missing credit still keeps the image.
     */
    public function attachImage(?string $url, ?string $author, ?string $authorUrl): void
    {
        if ($this->imageUrl !== null) {
            return; // already imaged — do not overwrite
        }
        $cleanUrl = $this->clean($url);
        if ($cleanUrl === null) {
            return;
        }
        $this->imageUrl = $cleanUrl;
        $this->imageAuthor = $this->clean($author);
        $this->imageAuthorUrl = $this->clean($authorUrl);
    }

    private function cleanIpa(?string $ipa): ?string
    {
        if ($ipa === null) {
            return null;
        }
        $trimmed = trim($ipa);

        return $trimmed !== '' ? $trimmed : null;
    }

    /** Trim to null: empty/whitespace-only strings become null so "no value" is one thing. */
    private function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);

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

    /** The found stock-photo URL, or null when none is attached yet / no match. */
    public function imageUrl(): ?string
    {
        return $this->imageUrl;
    }

    /** The model's image-search query for this term (server-internal), or null. */
    public function imageApiPrompt(): ?string
    {
        return $this->imageApiPrompt;
    }

    public function imageAuthor(): ?string
    {
        return $this->imageAuthor;
    }

    public function imageAuthorUrl(): ?string
    {
        return $this->imageAuthorUrl;
    }

    /**
     * Which prompt version and model wrote THIS term row, or null for a row that predates the
     * stamp or that a human typed.
     *
     * It belongs to the row and is never re-stamped on a dedup merge: a term created under one
     * prompt version keeps that version even when a later generation adds a translation to it, and
     * that later translation carries its own. Merging the two into one "the term's version" would
     * answer the sweep's question — "which prompt wrote the line I am reading" — with a guess.
     */
    public function provenance(): ?Provenance
    {
        return $this->provenance;
    }
}
