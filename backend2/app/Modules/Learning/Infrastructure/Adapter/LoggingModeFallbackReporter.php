<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Adapter;

use App\Modules\Learning\Application\Port\ModeFallbackReporter;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\Log;

final class LoggingModeFallbackReporter implements ModeFallbackReporter
{
    /** @param list<string> $enabledModes */
    public function noApplicableMode(UserId $userId, TermId $termId, array $enabledModes): void
    {
        // warning, not info: this is a configuration the user cannot see and cannot fix themselves.
        Log::warning('No enabled exercise mode fits this term; falling back to multiple_choice', [
            'user_id' => $userId->value,
            'term_id' => $termId->value,
            'enabled_modes' => $enabledModes,
        ]);
    }
}
