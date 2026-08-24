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
     * ## Exactly one primary per language — and it is the FIRST one, not the newest
     *
     * A term is global and deduplicated, so it accumulates translations: every regeneration of the
     * same text merges another reading in. Two rules have been needed here, in this order.
     *
     * A7 (20.08) established that a language may only have ONE primary. Before it the merge simply
     * appended, and a translation arriving marked primary landed BESIDE the primary already there —
     * ten live store terms ended up with two («stay calm» → «Оставайтесь спокойны» AND «оставаться
     * спокойным»), so the question on the card was whichever row the reader's ordering happened to
     * return. That rule stands and is what the invariant test pins.
     *
     * SYN-1 (25.08) reversed which of the two wins. A7 demoted the incumbent on the reasoning that
     * «a merge is the newer generation speaking»; that is true about MODELS and false about the
     * person reading the card. Nothing about a re-run is evidence that the learner's existing
     * question was wrong — but changing it is guaranteed to be felt, because the pinned translation
     * IS the question every card of that term asks. A second lookup of a word already saved, a
     * dedup merge from a generated collection, a re-enrichment: none of them may re-word a card
     * somebody is already learning from. So an arriving primary for a language that already has one
     * is stored as an ALTERNATIVE (`is_primary = false`) and the pin does not move.
     *
     * Nothing is lost either way — a demoted or unpromoted reading is a genuine alternative («cash
     * register» → «касса» beside «кассовый аппарат»), it stays queryable, and it is now shipped to
     * the client beside the primary ({@see \App\Modules\Vocabulary\Application\Dto\TermContentView::$translations}).
     * It simply does not compete to be the question.
     *
     * Moving the pin deliberately is {@see pinTranslation()}, and only the two authorities above a
     * generator call it: the learner (a translation they were shown in the translator and confirmed)
     * and a curator.
     *
     * An exact (lang,text) duplicate is not an arrival at all: nothing new was said, so nothing is
     * promoted or demoted. That is what keeps a re-run of the same generation a genuine no-op.
     */
    public function addTranslation(Translation $translation): void
    {
        foreach ($this->translations as $existing) {
            if ($existing->lang->equals($translation->lang) && $existing->text === $translation->text) {
                return;
            }
        }

        // A machine may fill an EMPTY pin, never move a set one.
        if ($translation->isPrimary && $this->hasPrimaryIn($translation->lang)) {
            $translation = $translation->demoted();
        }

        $this->translations[] = $translation;
    }

    /**
     * Move the pin: make this text the primary translation for its language, demoting whatever held
     * the pin before and adding the row if the term does not have it yet.
     *
     * The deliberate counterpart of {@see addTranslation()}'s refusal to move it. Reserved for the
     * two authorities the trust hierarchy puts above a generator — the learner confirming the
     * translation they were shown before pressing «Собрать карточку», and a curator correcting a
     * card by hand. A generator never calls this, which is the whole point of it being a separate
     * method rather than a flag on the other one.
     */
    public function pinTranslation(Translation $translation): void
    {
        $this->translations = array_map(
            static fn (Translation $existing): Translation => $existing->lang->equals($translation->lang)
                ? $existing->demoted()
                : $existing,
            $this->translations,
        );

        foreach ($this->translations as $index => $existing) {
            if ($existing->lang->equals($translation->lang) && $existing->text === $translation->text) {
                // Already there, merely not pinned: promote the ROW rather than adding a twin,
                // which the unique index would refuse anyway. Its provenance stays its own.
                $this->translations[$index] = new Translation(
                    $existing->lang,
                    $existing->text,
                    true,
                    $existing->provenance,
                );

                return;
            }
        }

        $this->translations[] = new Translation(
            $translation->lang,
            $translation->text,
            true,
            $translation->provenance,
        );
    }

    private function hasPrimaryIn(LanguageCode $lang): bool
    {
        foreach ($this->translations as $existing) {
            if ($existing->isPrimary && $existing->lang->equals($lang)) {
                return true;
            }
        }

        return false;
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
