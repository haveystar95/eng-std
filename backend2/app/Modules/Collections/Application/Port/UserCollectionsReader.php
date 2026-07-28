<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Port;

use App\Modules\Collections\Application\Dto\CollectionPage;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** Reads the user's own collections as summaries, cursor-paginated (newest first). */
interface UserCollectionsReader
{
    public function forUser(UserId $userId, ?string $cursor, int $limit): CollectionPage;
}
