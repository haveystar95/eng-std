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

    public function closedByLanguage(UserId $userId, TermId $termId, string $lang, string $reason): void
    {
        // warning, and a level above the other two in seriousness: a term whose language trains
        // nothing should never have reached a session at all (a reference collection does not
        // enrol), so this line is a report about the POOL, not about this card.
        Log::warning('No trainer exists for this term\'s language; no card was dealt', [
            'user_id' => $userId->value,
            'term_id' => $termId->value,
            'lang' => $lang,
            'reason' => $reason,
        ]);
    }

    public function tooFewOptions(UserId $userId, TermId $termId, string $mode, int $options): void
    {
        // warning for the same reason: the learner cannot see this and cannot fix it. The term needs
        // a neighbour or a distractor, which is content work, not a setting.
        Log::warning('A choice card was refused: too few options to be answerable', [
            'user_id' => $userId->value,
            'term_id' => $termId->value,
            'mode' => $mode,
            'options' => $options,
        ]);
    }
}
