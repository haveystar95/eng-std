<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Port\LineSpeechReporter;
use Illuminate\Support\Facades\Log;

/** {@see LineSpeechReporter} — две строки в логе, и ничего больше. */
final class LoggingLineSpeechReporter implements LineSpeechReporter
{
    public function pass(string $collectionId, string $voiceKey, int $spoken, int $missing): void
    {
        $context = [
            'collection_id' => $collectionId,
            'voice' => $voiceKey,
            'spoken' => $spoken,
            'missing' => $missing,
        ];

        // Недоозвученные — предупреждение; полный проход — info. Разница уровней и есть фильтр,
        // которым «озвучка отстала» ищется в логе за неделю.
        $missing > 0
            ? Log::warning('plan lines left without audio', $context)
            : Log::info('plan lines voiced', $context);
    }

    public function refused(string $termId, string $voiceKey, string $reason): void
    {
        Log::warning('speech vendor refused a line', [
            'term_id' => $termId,
            'voice' => $voiceKey,
            'reason' => $reason,
        ]);
    }
}
