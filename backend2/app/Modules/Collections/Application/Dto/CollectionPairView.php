<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Dto;

/**
 * One collection's LANGUAGE PAIR, and nothing else.
 *
 * The names are the column names, and they read backwards from the product's own words, so they
 * are spelled out once here rather than guessed at every call site:
 *
 *  - `targetLang` — the language BEING LEARNED. It is the language of every term in this
 *    collection ({@see \App\Modules\Collections\Domain\Entity\Collection::assertAcceptsTerm()}),
 *    of its examples and of its descriptions.
 *  - `sourceLang` — the language of SUPPORT. Which of a term's several translations is the one
 *    this collection shows, and the language the gloss under an example is written in.
 *
 * A collection has exactly one pair and accepts only terms of it (DECISIONS п. 81), which is what
 * makes this two scalars rather than a set: «одна папка — одна пара».
 */
final readonly class CollectionPairView
{
    public function __construct(
        public string $targetLang,
        public string $sourceLang,
    ) {}
}
