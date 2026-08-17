<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;

/** Erase a user's generation requests (prompts, tokens, cost) on account deletion. */
interface GenerationAccountEraser
{
    public function eraseFor(UserId $userId): void;
}
