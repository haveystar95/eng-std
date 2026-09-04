<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Shared\Domain\ValueObject\LineVoice;
use App\Modules\Vocabulary\Application\Port\TermAudioWriter;
use Illuminate\Support\Facades\DB;

final class EloquentTermAudioWriter implements TermAudioWriter
{
    public function write(
        string $audioId,
        string $termId,
        LineVoice $voice,
        string $format,
        string $path,
        int $bytes,
        ?int $durationMs,
        ?string $costUsd,
    ): bool {
        // `insertOrIgnore` и есть проверка: конфликт по `term_audios_voice_uidx` — это «уже есть»,
        // а не сбой. Ловить его SELECT-ом перед вставкой бессмысленно, между ними живёт второй воркер.
        $inserted = DB::table('term_audios')->insertOrIgnore([
            'id' => $audioId,
            'term_id' => $termId,
            'voice' => $voice->key(),
            'variant' => $voice->variant(),
            'provider' => $voice->provider,
            'format' => $format,
            'path' => $path,
            'bytes' => $bytes,
            'duration_ms' => $durationMs,
            'cost_usd' => $costUsd,
            'created_at' => now(),
        ]);

        return $inserted > 0;
    }
}
