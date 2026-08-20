<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

use App\Modules\Admin\Application\Dto\CollectionRefRow;
use App\Modules\Admin\Application\Dto\ContentHealthCollectionRow;
use App\Modules\Admin\Application\Dto\ContentHealthScope;
use App\Modules\Admin\Application\Dto\ContentHealthSummary;
use App\Modules\Admin\Application\Dto\ContentHealthTermRow;
use App\Modules\Admin\Application\Dto\ContentLabelCount;
use App\Modules\Admin\Application\Dto\ContentVersionCount;
use App\Modules\Admin\Application\Port\AdminContentHealthReader;
use App\Modules\Admin\Application\Service\ContentHealthAssessor;
use App\Modules\Admin\Application\Service\ContentTopUp;

/**
 * Counts the whole dictionary once, then slices it: the base, the system collections, the user ones,
 * and every collection on its own line.
 *
 * One pass over one read. The alternative — a COUNT query per counter — would ask Postgres the same
 * question a dozen times AND would have to express «годный дистрактор» in SQL, which is a rule that
 * already exists in Learning and must not acquire a second spelling here.
 */
final readonly class GetContentHealthSummaryHandler
{
    private const SYSTEM_TYPE = 'system';

    public function __construct(
        private AdminContentHealthReader $content,
        private ContentHealthAssessor $assessor,
        private ContentTopUp $topUp,
    ) {}

    public function __invoke(GetContentHealthSummary $query): ContentHealthSummary
    {
        $collections = $this->content->collections();
        /** @var array<string, CollectionRefRow> $byId */
        $byId = [];
        foreach ($collections as $collection) {
            $byId[$collection->id] = $collection;
        }

        /** @var list<ContentHealthTermRow> $all */
        $all = [];
        /** @var list<ContentHealthTermRow> $system */
        $system = [];
        /** @var list<ContentHealthTermRow> $user */
        $user = [];
        /** @var array<string, list<ContentHealthTermRow>> $perCollection */
        $perCollection = [];

        foreach ($this->content->termFacts() as $facts) {
            $row = $this->assessor->row($facts);
            $all[] = $row;

            $inSystem = false;
            $inUser = false;
            foreach ($facts->collectionIds as $collectionId) {
                $collection = $byId[$collectionId] ?? null;
                if ($collection === null) {
                    continue;
                }
                $perCollection[$collectionId][] = $row;
                // A term can sit in both a store collection and someone's own list; it then counts
                // in both slices, which is why the three rows are not meant to add up.
                if ($collection->type === self::SYSTEM_TYPE) {
                    $inSystem = true;
                } else {
                    $inUser = true;
                }
            }
            if ($inSystem) {
                $system[] = $row;
            }
            if ($inUser) {
                $user[] = $row;
            }
        }

        $suppressions = $this->content->suppressionsBySource();
        $rejections = $this->content->rejectionsByField();

        return new ContentHealthSummary(
            all: $this->scope('all', $all),
            system: $this->scope('system', $system),
            user: $this->scope('user', $user),
            collections: array_map(
                fn (CollectionRefRow $c): ContentHealthCollectionRow => $this->collectionRow($c, $perCollection[$c->id] ?? []),
                $collections,
            ),
            suppressionsTotal: $this->total($suppressions),
            suppressionsBySource: $suppressions,
            rejectionsTotal: $this->total($rejections),
            rejectionsByField: $rejections,
            currentGeneratorVersion: $this->topUp->currentVersion(),
            minDistractors: ContentTopUp::MIN_DISTRACTORS,
            costPerTermUsd: ContentTopUp::COST_PER_TERM_USD,
        );
    }

    /** @param  list<ContentHealthTermRow>  $rows */
    private function scope(string $scope, array $rows): ContentHealthScope
    {
        $needs = $this->count($rows, static fn (ContentHealthTermRow $r): bool => $r->needsEnrichment);

        return new ContentHealthScope(
            scope: $scope,
            terms: count($rows),
            withDistractors: $this->count($rows, static fn (ContentHealthTermRow $r): bool => $r->usableDistractors > 0),
            pickCorrectReady: $this->count($rows, static fn (ContentHealthTermRow $r): bool => $r->pickCorrectReady),
            withVariants: $this->count($rows, static fn (ContentHealthTermRow $r): bool => $r->variants > 0),
            withoutExample: $this->count($rows, static fn (ContentHealthTermRow $r): bool => $r->missingExample),
            needsEnrichment: $needs,
            estimatedTopUpUsd: $this->topUp->estimateUsd($needs),
            enrichmentVersions: $this->versions($rows),
        );
    }

    /** @param  list<ContentHealthTermRow>  $rows */
    private function collectionRow(CollectionRefRow $collection, array $rows): ContentHealthCollectionRow
    {
        $needs = $this->count($rows, static fn (ContentHealthTermRow $r): bool => $r->needsEnrichment);

        return new ContentHealthCollectionRow(
            id: $collection->id,
            title: $collection->title,
            type: $collection->type,
            terms: count($rows),
            withoutExample: $this->count($rows, static fn (ContentHealthTermRow $r): bool => $r->missingExample),
            pickCorrectReady: $this->count($rows, static fn (ContentHealthTermRow $r): bool => $r->pickCorrectReady),
            needsEnrichment: $needs,
            estimatedTopUpUsd: $this->topUp->estimateUsd($needs),
        );
    }

    /**
     * @param  list<ContentHealthTermRow>  $rows
     * @return list<ContentVersionCount>  «не прогонялся» (null) first — the bucket that matters
     */
    private function versions(array $rows): array
    {
        $never = 0;
        /** @var array<string, int> $counts */
        $counts = [];
        foreach ($rows as $row) {
            if ($row->enrichmentVersion === null) {
                $never++;

                continue;
            }
            $counts[$row->enrichmentVersion] = ($counts[$row->enrichmentVersion] ?? 0) + 1;
        }
        krsort($counts);

        $out = [];
        if ($never > 0) {
            $out[] = new ContentVersionCount(null, $never);
        }
        foreach ($counts as $version => $count) {
            $out[] = new ContentVersionCount((string) $version, $count);
        }

        return $out;
    }

    /**
     * @param  list<ContentHealthTermRow>  $rows
     * @param  callable(ContentHealthTermRow): bool  $predicate
     */
    private function count(array $rows, callable $predicate): int
    {
        return count(array_filter($rows, $predicate));
    }

    /** @param  list<ContentLabelCount>  $counts */
    private function total(array $counts): int
    {
        return array_sum(array_map(static fn (ContentLabelCount $c): int => $c->count, $counts));
    }
}
