<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Query;

use App\Modules\Identity\Application\Dto\PushTokenView;
use App\Modules\Identity\Application\Port\PushTokenStore;

/** Every push address of the learner — what a sender in another module fans a letter out to. */
final readonly class GetPushTokensHandler
{
    public function __construct(private PushTokenStore $tokens) {}

    /** @return list<PushTokenView> */
    public function __invoke(GetPushTokens $query): array
    {
        return $this->tokens->forUser($query->userId);
    }
}
