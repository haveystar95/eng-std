<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Service;

use App\Modules\Generation\Application\Command\BuildTermEnrichmentsHandler;

/**
 * When a term is worth paying the станок for, what that costs, and the exact line to type.
 *
 * A LINE OF TEXT, not a button, and that is the whole design. The станок spends real money against
 * a real model; a panel that could start it would put an unbounded bill one misclick away, and an
 * operator who has to paste a command into a terminal has read what it will do. The panel's job
 * here is to know WHICH command — which collections, which threshold — so nobody assembles it from
 * memory and runs the wrong one.
 */
final readonly class ContentTopUp
{
    /**
     * The stocking target for a pinned example: THREE usable distractors, not the card's two.
     *
     * `pick_correct` deals one correct sentence and two wrong ones
     * ({@see \App\Modules\Learning\Domain\ValueObject\TermPlayability::MIN_PICK_CORRECT_DISTRACTORS}),
     * so two is the floor at which the card exists at all. Three is what the станок is asked for,
     * because a proofreader's deletion or an audit's `--apply` takes rows away afterwards and a term
     * sitting exactly on the floor drops off it the first time someone rejects a sentence. The
     * report therefore flags at three: «сколько терминов стоит догнать», not «сколько сломано».
     */
    public const MIN_DISTRACTORS = 3;

    /** Measured over the live runs — one term through the станок, in and out. */
    public const COST_PER_TERM_USD = 0.004;

    /**
     * A live example, and fewer than {@see MIN_DISTRACTORS} usable distractors on it.
     *
     * No example → the станок has nothing to write against, so this is NOT a догон case (it is fixed
     * by regenerating the example) and must not be counted into the bill.
     *
     * **An empty variant list is deliberately NOT a reason.** It was one until the prompt learned
     * what a `form` is: an alternative SPELLING of the same term («organise»/«organize»,
     * «check-in»/«check in»), not a synonym. Most terms genuinely have none — the prompt says so to
     * the model in as many words — so treating zero as a defect lit the flag on every term in the
     * dictionary, permanently, and quoted a bill for re-running terms that will never produce one.
     * A report that asks for money on every row teaches the reader to stop looking at the column.
     */
    public function needsEnrichment(bool $hasExample, int $usableDistractors, int $variants): bool
    {
        return $hasExample && $usableDistractors < self::MIN_DISTRACTORS;
    }

    /**
     * @param  int  $variants  kept in the signature: the caller has it, and the day a variant deficit
     *         becomes diagnosable again this is where it belongs. See {@see needsEnrichment()}.
     * @return list<string>  the machine reasons behind {@see needsEnrichment()}, in report order
     */
    public function reasons(bool $hasExample, int $usableDistractors, int $variants): array
    {
        if (! $hasExample || $usableDistractors >= self::MIN_DISTRACTORS) {
            return [];
        }

        return ['few_distractors'];
    }

    public function estimateUsd(int $terms): float
    {
        return round($terms * self::COST_PER_TERM_USD, 4);
    }

    /**
     * The догон line for these collections.
     *
     * `--topup` and not a plain run on purpose: the plain path skips every term already marked at
     * the current version, and a term whose distractors a proofreader DELETED is exactly such a term
     * — processed, and short. `--topup` asks about coverage instead and ignores the mark, which is
     * why the command below carries no `--generator`.
     *
     * @param  list<string>  $collectionIds
     */
    public function command(array $collectionIds): string
    {
        $flags = implode(' ', array_map(
            static fn (string $id): string => "--collection={$id}",
            $collectionIds,
        ));

        return trim("php artisan enrich:backfill {$flags} --topup=" . self::MIN_DISTRACTORS);
    }

    /** The version a plain (non-topup) run writes and skips by today. */
    public function currentVersion(): string
    {
        return BuildTermEnrichmentsHandler::VERSION;
    }

    /**
     * The version-skip lesson, stated only when it applies to THIS term.
     *
     * A term already marked at the current version is invisible to a plain run — «nothing to do» —
     * and someone who has just been told the term is under-stocked will read that as the станок
     * being broken. The догон above sidesteps it; a re-run for any other reason needs a different
     * `--generator`.
     */
    public function versionHint(?string $termVersion): ?string
    {
        if ($termVersion === null || $termVersion !== $this->currentVersion()) {
            return null;
        }

        return "Термин уже помечен текущей версией станка ({$termVersion}): обычный прогон его пропустит "
            . '(«nothing to do»). Догон выше идёт через --topup и метку версии игнорирует; если нужен '
            . 'полный перепрогон, задайте другую --generator=<версия> (опция называется именно так — '
            . '--version занята самим artisan).';
    }
}
