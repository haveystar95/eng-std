<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Command;

use App\Modules\Vocabulary\Domain\Repository\TermRepository;

final readonly class AttachTermImageHandler
{
    public function __construct(private TermRepository $terms) {}

    public function __invoke(AttachTermImage $command): void
    {
        $term = $this->terms->findById($command->termId);
        if ($term === null) {
            return; // the caller passes ids it just read; a vanished term is a no-op, not an error
        }

        $term->attachImage($command->imageUrl, $command->imageAuthor, $command->imageAuthorUrl);
        $this->terms->save($term);
    }
}
