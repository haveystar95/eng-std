<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Command;

use App\Modules\Shared\Domain\ValueObject\TermId;

/**
 * Attach a found stock photo to a term. Cross-module entry point (Generation's attach job calls it)
 * built from primitives. Idempotent and never-overwrite: the aggregate ignores it if the term is
 * already imaged, which is what keeps a globally-shared term's first photo stable.
 */
final readonly class AttachTermImage
{
    public function __construct(
        public TermId $termId,
        public ?string $imageUrl,
        public ?string $imageAuthor,
        public ?string $imageAuthorUrl,
    ) {}
}
