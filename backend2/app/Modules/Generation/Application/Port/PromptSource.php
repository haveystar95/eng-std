<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Generation\Application\Dto\RenderedPrompt;
use App\Modules\Generation\Domain\ValueObject\PromptShape;

/**
 * Where a rendered prompt comes from, as far as Application is concerned: a version and a shape in,
 * finished text plus its digest out.
 *
 * The seam exists because prompts are FILES, which makes reading them an Infrastructure concern,
 * while deciding which version to run is an Application one. Without the port the bake-off would
 * have to reach into Infrastructure for its prompt — the one dependency direction this module does
 * not allow, and the reason is not ceremony: it is what keeps the run replaceable by a stored prompt
 * later without touching the thing that runs it.
 */
interface PromptSource
{
    /** @param array<string, string> $placeholders keys WITHOUT the braces */
    public function render(string $version, PromptShape $shape, array $placeholders): RenderedPrompt;
}
