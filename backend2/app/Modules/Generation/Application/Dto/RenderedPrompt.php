<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

use App\Modules\Generation\Domain\ValueObject\PromptShape;

/**
 * A prompt as it was actually sent: the finished text, plus what identifies it afterwards.
 *
 * `sha256` is over the RENDERED text, placeholders already substituted. The version string says
 * which edition of the rules was asked for; the digest proves which bytes were sent, and it is the
 * only thing that catches a section file edited without the version being bumped. A comparison of
 * two runs that only matched on `v10` would be comparing two different prompts and calling the
 * difference a model difference.
 */
final readonly class RenderedPrompt
{
    public function __construct(
        public string $text,
        public string $version,
        public PromptShape $shape,
        public string $sha256,
    ) {}

    /** Short digest for a report header — full digests turn a table into a wall of hex. */
    public function shortSha(): string
    {
        return substr($this->sha256, 0, 12);
    }
}
