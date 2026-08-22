<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

use DateTimeImmutable;

/**
 * A stored lookup answer, as it is handed to the client and later turned into a term.
 *
 * The `id` is the handle `POST /search/add` takes: the learner looks at a card and then says «save
 * this one», and pointing at the row we already have is what keeps the two steps from being two
 * different words (a re-generation between the two calls could legitimately word things
 * differently, and the learner would save something they never read).
 */
final readonly class CachedLookup
{
    public function __construct(
        public string $id,
        public string $normalizedQuery,
        public string $lang,
        public string $nativeLang,
        public string $text,
        public string $type,
        public string $translation,
        public string $description,
        public ?string $example,
        public ?string $exampleTranslation,
        public ?string $cefr,
        public ?string $transcription,
        /** The model's image-search query. Null on rows cached before the v2 prompt existed. */
        public ?string $imageApiPrompt,
        public string $model,
        public string $promptVersion,
        public DateTimeImmutable $createdAt,
        /** True when this call was paid for right now rather than served from the cache. */
        public bool $fresh = false,
        /**
         * Was the word's ILLUSTRATION question asked at all when this row was written?
         *
         * Not «does it have a photo query» — `imageApiPrompt` being null answers that, and null is a
         * legitimate answer: the prompt asks for an empty query when a word has no honest picture.
         * This is the older, sharper question: rows written before the v2 prompt existed were never
         * asked, so their null means «unknown», not «refused».
         *
         * The distinction earns its keep because the cache is GLOBAL and permanent: without it, the
         * first person ever to look a word up freezes that word's card at whatever the prompt could
         * produce that day, for everybody, forever. A cache should hold answers, not the shape of
         * the question that happened to be asked first.
         */
        public bool $illustrationDecided = true,
        /**
         * The model could not place this query in either language.
         *
         * Cached exactly like a card, because it is the same kind of fact: `asdfgh` will not become
         * a word tomorrow. Caching it is also what keeps the daily cap honest — the cap counts rows,
         * so a refusal that wrote none would be a paid call nobody was charged for, and a field
         * that costs nothing to abuse is a field that gets abused.
         */
        public bool $notRecognized = false,
    ) {}
}
