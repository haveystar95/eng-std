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

    /** The version both prompts are stamped with — what lands in `terms.prompt_version`. */
    public function version(): string;
}
