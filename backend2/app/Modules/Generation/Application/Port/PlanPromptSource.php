<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Generation\Application\Dto\RenderedPrompt;

/**
 * Where a PLAN prompt comes from. Same seam as {@see PromptSource} and a separate one, because the
 * two catalogues are addressed differently and merging them would mean inventing a
 * {@see \App\Modules\Generation\Domain\ValueObject\PromptShape} case for «the skeleton of a plan»,
 * which is not a shape of a collection generation at all.
 */
interface PlanPromptSource
{
    /** @param array<string, string> $placeholders keys WITHOUT the braces */
    public function outline(array $placeholders): RenderedPrompt;

    /** @param array<string, string> $placeholders keys WITHOUT the braces */
    public function day(array $placeholders): RenderedPrompt;

    /**
     * P2R — a handful of broken cards of a day that is otherwise accepted.
     *
     * @param  array<string, string>  $placeholders  keys WITHOUT the braces
     */
    public function repair(array $placeholders): RenderedPrompt;

    /**
     * The version each prompt is stamped with — what lands in the ledger row and in
     * `terms.prompt_version`.
     *
     * THREE versions and no longer one. P1, P2 and P2R are revised separately (v0.2 moved the
     * skeleton off days before it moved the day off two arrays), and a single number for all of
     * them meant that bumping any one stamped the others with a version they were not written at.
     * A ledger row that names the wrong prompt is worse than no row: it is the row a later run
     * will trust.
     */
    public function outlineVersion(): string;

    public function dayVersion(): string;

    public function repairVersion(): string;
}
