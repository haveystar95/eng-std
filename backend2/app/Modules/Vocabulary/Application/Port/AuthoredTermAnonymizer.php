<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * On account deletion, sever the authorship link: terms are global and deduplicated, shared by
 * everyone, so they stay — only `terms.created_by` is nulled where it pointed at the leaving user.
 */
interface AuthoredTermAnonymizer
{
    public function anonymizeAuthor(UserId $userId): void;
}
