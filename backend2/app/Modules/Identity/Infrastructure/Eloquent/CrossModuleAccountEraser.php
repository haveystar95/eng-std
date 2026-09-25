<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Eloquent;

use App\Modules\Admin\Application\Port\AdminAuditAnonymizer;
use App\Modules\Collections\Application\Port\CollectionsAccountEraser;
use App\Modules\Generation\Application\Port\GenerationAccountEraser;
use App\Modules\Identity\Application\Port\AccountEraser;
use App\Modules\Identity\Domain\Exception\AccountNotFound;
use App\Modules\Learning\Application\Port\LearningAccountEraser;
use App\Modules\Observability\Application\Port\RequestLogAnonymizer;
use App\Modules\Plan\Application\Port\PlanAccountEraser;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use App\Modules\Vocabulary\Application\Port\AuthoredTermAnonymizer;
use Illuminate\Support\Facades\DB;

/**
 * Deletes an account by fanning out to each module's own Application eraser — no module's tables are touched directly
 * from here. The whole cascade runs in one transaction so a mid-way failure leaves the account intact rather than
 * half-deleted, and it begins by LOCKING the account's row: two deletions of one account wait for each other, and the
 * second finds it gone — 404 `account_not_found` (наряд ACC-1 §1: «идемпотентно, повтор — 404»).
 *
 * What goes: the plans with everything on them and their files (after the commit), the collections and the pool, the
 * generations and the practice dialogs, the tokens, the user row — the profile, the devices' push addresses, the visits,
 * the access rights and the per-user trainer overrides by FK cascade. What stays, unlinked: the global terms (authorship
 * nulled), the request log (user and what its rows carried of the person nulled), the admin audit (its target nulled),
 * the model call journal (it names no user). One line of `account_deletions` keeps the count: an HMAC of the id under
 * the application key, the moment, and how many plans there were.
 */
final readonly class CrossModuleAccountEraser implements AccountEraser
{
    public function __construct(
        private TransactionManager $tx,
        private Clock $clock,
        private CollectionsAccountEraser $collections,
        private LearningAccountEraser $learning,
        private GenerationAccountEraser $generations,
        private PlanAccountEraser $plans,
        private AuthoredTermAnonymizer $authoredTerms,
        private RequestLogAnonymizer $logs,
        private AdminAuditAnonymizer $audit,
    ) {}

    public function eraseFor(UserId $userId): void
    {
        $this->tx->run(function () use ($userId): void {
            $user = User::query()->whereKey($userId->value)->lockForUpdate()->first();
            if ($user === null) {
                throw AccountNotFound::withId($userId->value);
            }

            // Plans first: their collection is one of the learner's and goes with the rest below.
            $plans = $this->plans->eraseFor($userId);
            $this->collections->eraseFor($userId);
            $this->learning->eraseFor($userId);
            $this->generations->eraseFor($userId);
            $this->authoredTerms->anonymizeAuthor($userId);
            $this->logs->anonymizeUser($userId);
            $this->audit->anonymizeTarget($userId);

            DB::table('account_deletions')->insert([
                'id' => Ulid::generate(),
                'user_hash' => hash_hmac('sha256', $userId->value, (string) config('app.key')),
                'plans_count' => $plans,
                'deleted_at' => $this->clock->now(),
            ]);

            $user->tokens()->delete();   // revoke every Sanctum token
            $user->delete();             // profile, push tokens, visits, access rights, overrides — by FK cascade
        });
    }
}
