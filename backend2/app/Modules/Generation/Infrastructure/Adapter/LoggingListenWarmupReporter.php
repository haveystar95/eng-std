<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Port\ListenWarmupReporter;
use Illuminate\Support\Facades\Log;

/** {@see ListenWarmupReporter} — a warning line, and nothing else. */
final class LoggingListenWarmupReporter implements ListenWarmupReporter
{
    public function notOffered(string $userId, string $targetLang, string $reason): void
    {
        Log::warning('listening warm-up was not offered', [
            'user_id' => $userId,
            'target_lang' => $targetLang,
            'reason' => $reason,
        ]);
    }
}
