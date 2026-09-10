<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Vocabulary\Application\Query\NativeDistractorReader;
use Illuminate\Support\Facades\DB;

/**
 * Primary native-language translations of catalogue words, filtered by length around `$like`.
 * Ordered by CEFR then id so the same day asks for the same options; `terms.source = 'user'` is
 * never read, for the reason {@see \App\Modules\Vocabulary\Application\Query\DistractorReader} gives.
 */
final class EloquentNativeDistractorReader implements NativeDistractorReader
{
    public function translations(LanguageCode $targetLang, LanguageCode $nativeLang, string $like, array $exclude, int $count): array
    {
        $length = max(3, mb_strlen(trim($like)));
        $rows = DB::table('term_translations as tt')
            ->join('terms as t', 't.id', '=', 'tt.term_id')
            ->where('t.lang', $targetLang->value)
            ->where('t.type', 'word')
            ->where('t.source', '!=', 'user')
            ->whereNull('t.deleted_at')
            ->where('tt.lang', $nativeLang->value)
            ->where('tt.is_primary', true)
            ->whereRaw('length(tt.text) BETWEEN ? AND ?', [max(2, (int) floor($length * 0.5)), (int) ceil($length * 1.5)])
            ->orderBy('t.cefr')
            ->orderBy('t.id')
            ->limit($count * 4)
            ->pluck('tt.text');

        $seen = array_flip(array_map(static fn (string $s): string => mb_strtolower(trim($s)), $exclude));
        $out = [];
        foreach ($rows as $text) {
            $key = mb_strtolower(trim((string) $text));
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = trim((string) $text);
            if (count($out) >= $count) {
                break;
            }
        }

        return $out;
    }
}
