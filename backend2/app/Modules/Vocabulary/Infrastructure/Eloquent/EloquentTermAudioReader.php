<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Shared\Domain\ValueObject\LineVoice;
use App\Modules\Vocabulary\Application\Dto\TermAudioRow;
use App\Modules\Vocabulary\Application\Query\TermAudioReader;
use Illuminate\Support\Facades\DB;

final class EloquentTermAudioReader implements TermAudioReader
{
    public function forTerms(array $termIds, LineVoice $voice): array
    {
        if ($termIds === []) {
            return [];
        }

        $rows = DB::table('term_audios')
            ->whereIn('term_id', $termIds)
            ->where('voice', $voice->key())
            ->where('variant', $voice->variant())
            ->get(['id', 'term_id', 'voice', 'variant', 'format', 'path', 'bytes', 'duration_ms']);

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->term_id] = self::hydrate((array) $row);
        }

        return $out;
    }

    public function missingFor(array $termIds, LineVoice $voice): array
    {
        if ($termIds === []) {
            return [];
        }

        $have = array_keys($this->forTerms($termIds, $voice));

        return array_values(array_diff($termIds, $have));
    }

    public function byId(string $audioId): ?TermAudioRow
    {
        $row = DB::table('term_audios')
            ->where('id', $audioId)
            ->first(['id', 'term_id', 'voice', 'variant', 'format', 'path', 'bytes', 'duration_ms']);

        return $row === null ? null : self::hydrate((array) $row);
    }

    /** @param array<string, mixed> $row */
    private static function hydrate(array $row): TermAudioRow
    {
        return new TermAudioRow(
            id: (string) $row['id'],
            termId: (string) $row['term_id'],
            voice: (string) $row['voice'],
            variant: (string) $row['variant'],
            format: (string) $row['format'],
            path: (string) $row['path'],
            bytes: (int) $row['bytes'],
            durationMs: isset($row['duration_ms']) ? (int) $row['duration_ms'] : null,
        );
    }
}
