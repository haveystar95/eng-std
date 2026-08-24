<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * «Сколько усвоено в румынском» — the same counters as {@see CollectionProgressView}, cut by the
 * language of the TERM instead of by the folder it sits in (DECISIONS п. 139).
 *
 * BY LANGUAGE, NEVER BY PAIR. Progress lives on `(user, term)` and a term has exactly one language,
 * so this cut is a regrouping of the very same rows — no second progress is introduced and none can
 * be. A cut by PAIR would be a different and false thing: one word studied through a `ru→en` folder
 * and an `uk→en` folder would land in two buckets and be counted twice, and the app would show more
 * words mastered than exist.
 *
 * A TERM IS COUNTED ONCE. The per-collection view counts a word once per folder — which is right
 * there, because that is the folder's own bar — and the same word in three folders would be three
 * words here.
 */
final readonly class LanguageProgressView
{
    public function __construct(
        public string $lang,      // the language the TERMS are written in
        public int $total,        // distinct terms in this language across the user's collections
        public int $newCount,     // not started (no row, or returned to new)
        public int $due,          // studied terms due now
        public int $mastered,     // confirmed + familiar (the one «усвоено»)
        public int $confirmed,    // proven by exercises: review & interval >= 21
        public int $familiar,     // marked known (self-assessed, awaiting proof)
        public int $inProgress,   // started but not yet mastered
    ) {}
}
