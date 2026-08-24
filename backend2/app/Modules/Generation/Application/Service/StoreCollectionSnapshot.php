<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Service;

use App\Modules\Collections\Application\Query\GetCollectionTermSet;
use App\Modules\Collections\Application\Query\GetCollectionTermSetHandler;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Dto\SupportLanguages;
use App\Modules\Vocabulary\Application\Query\TermContentReader;

/**
 * What a live collection holds right now, for the bake-off's baseline column.
 *
 * One of the topics handed to the providers is deliberately a collection that already exists, and
 * the question a reader has about that row is not "which provider looks better" but "is any of this
 * better than what the learner is being shown today". Without the store's own list beside them,
 * that comparison is a memory exercise.
 *
 * Two cross-module reads, both through the other modules' Application — which is also why this is
 * not a private method on the console command: a command may not reach into Collections or
 * Vocabulary, and it is not the place where "what counts as the baseline" is decided.
 *
 * READ ONLY. Nothing here writes, and the rows it returns are copied into a document.
 */
final readonly class StoreCollectionSnapshot
{
    public function __construct(
        private GetCollectionTermSetHandler $termSet,
        private TermContentReader $content,
    ) {}

    /**
     * The collection's title and its terms in order, or null when there is no such collection.
     *
     * The language is the COLLECTION's own (`source_lang`), not a parameter: the question this
     * baseline answers is «что учащемуся показывают СЕГОДНЯ», and today he is shown this deck's
     * pair. A run's `--source-lang` names the pair of the CANDIDATES, and reading the baseline in
     * it would print an empty column the moment the two differ.
     *
     * @return array{title: string, terms: list<array{text: string, translation: string}>}|null
     */
    public function read(string $collectionId): ?array
    {
        $set = ($this->termSet)(new GetCollectionTermSet(CollectionId::fromString($collectionId)));
        if ($set === null) {
            return null;
        }

        $views = $this->content->byIds(
            array_map(static fn (string $id): TermId => TermId::fromString($id), $set->termIds),
            SupportLanguages::uniform($set->sourceLang),
        );

        $terms = [];
        foreach ($set->termIds as $id) {
            $view = $views[$id] ?? null;
            if ($view !== null) {
                // A term with no translation in this language is shown as such rather than skipped:
                // a hole in the baseline is itself a fact about the collection.
                $terms[] = ['text' => $view->text, 'translation' => $view->translation ?? '—'];
            }
        }

        return ['title' => $set->title, 'terms' => $terms];
    }
}
