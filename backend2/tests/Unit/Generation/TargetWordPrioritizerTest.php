<?php

declare(strict_types=1);

use App\Modules\Generation\Domain\Service\TargetWordPrioritizer;

it('orders due first, then new, then known, capped at the limit', function () {
    $prioritizer = new TargetWordPrioritizer();

    // t3 is due; t2 is started-but-not-due (known); t1/t4/t5 are new.
    $selected = $prioritizer->select(
        collectionTermIds: ['t1', 't2', 't3', 't4', 't5'],
        dueTermIds: ['t3'],
        startedTermIds: ['t2' => true, 't3' => true],
        limit: 8,
    );

    expect($selected)->toBe(['t3', 't1', 't4', 't5', 't2']);
});

it('caps the selection at the limit', function () {
    $prioritizer = new TargetWordPrioritizer();

    expect($prioritizer->select(['a', 'b', 'c', 'd'], [], [], 2))->toBe(['a', 'b']);
});

it('ignores due ids that are not in this collection', function () {
    $prioritizer = new TargetWordPrioritizer();

    expect($prioritizer->select(['a', 'b'], ['not-here'], [], 8))->toBe(['a', 'b']);
});

it('treats a started row as known, not new', function () {
    $prioritizer = new TargetWordPrioritizer();

    // b started but not due → it sorts after the new words.
    expect($prioritizer->select(['a', 'b', 'c'], [], ['b' => true], 8))->toBe(['a', 'c', 'b']);
});
