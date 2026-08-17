<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Eloquent;

use App\Modules\Collections\Application\Port\CollectionsAccountEraser;
use App\Modules\Generation\Application\Port\GenerationAccountEraser;
use App\Modules\Identity\Application\Port\AccountEraser;
use App\Modules\Learning\Application\Port\LearningAccountEraser;
use App\Modules\Observability\Application\Port\RequestLogAnonymizer;
use App\Modules\Shared\Domain\Service\TransactionManager;
use App\Modules\Shared\Domain\ValueObject\UserId;
use App\Modules\Vocabulary\Application\Port\AuthoredTermAnonymizer;

/**
 * Deletes an account by fanning out to each module's own Application eraser — no module's tables
 * are touched directly from here. The whole cascade runs in one transaction so a mid-way failure
 * leaves the account intact rather than half-deleted. Terms stay global (authorship nulled); the
 * audit log stays (user_id nulled); the user's tokens are revoked and the user row (with its
 * profile, by FK cascade) is removed last.
 */
final readonly class CrossModuleAccountEraser implements AccountEraser
{
    public function __construct(
        private TransactionManager $tx,
        private CollectionsAccountEraser $collections,
        private LearningAccountEraser $learning,
        private GenerationAccountEraser $generations,
        private AuthoredTermAnonymizer $authoredTerms,
        private RequestLogAnonymizer $logs,
    ) {}

    public function eraseFor(UserId $userId): void
    {
        $this->tx->run(function () use ($userId): void {
            $this->collections->eraseFor($userId);
            $this->learning->eraseFor($userId);
            $this->generations->eraseFor($userId);
            $this->authoredTerms->anonymizeAuthor($userId);
            $this->logs->anonymizeUser($userId);

            $user = User::query()->find($userId->value);
            if ($user !== null) {
                $user->tokens()->delete();   // revoke every Sanctum token
                $user->delete();             // profiles cascade via FK
            }
        });
    }
}
