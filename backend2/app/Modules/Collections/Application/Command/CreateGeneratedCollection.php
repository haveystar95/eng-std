<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Command;

use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** Create an AI-sourced personal collection (used by the Generation module). */
final readonly class CreateGeneratedCollection
{
    /**
     * The origin tag for a PLAN DAY's folder — the one value {@see \App\Modules\Collections\Domain\ValueObject\CollectionOrigin}
     * has today, spelled here as a constant so the caller cannot mistype it.
     *
     * A string on this command rather than the enum itself, because the caller is another module's
     * Application layer and may not reach into Collections' Domain — the same shape `ImportTerm`
     * already uses for `source: 'ai'`.
     */
    public const ORIGIN_PLAN = 'plan';

    public function __construct(
        public UserId $ownerId,
        public string $title,
        public LanguageCode $sourceLang,
        public LanguageCode $targetLang,
        public ?string $description = null,
        public ?string $topic = null,
        public ?string $imageApiPrompt = null,   // model's cover-image query, for AttachImagesJob
        /**
         * WHERE THIS FOLDER CAME FROM — {@see ORIGIN_PLAN}, or null for every other generation.
         *
         * A plan day owns an ordinary collection on purpose and must not therefore show up as one
         * of the learner's own shelves (Д-34, Д-35).
         */
        public ?string $origin = null,
    ) {}
}
