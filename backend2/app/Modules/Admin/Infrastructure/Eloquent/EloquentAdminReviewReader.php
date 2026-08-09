<?php

declare(strict_types=1);

namespace App\Modules\Admin\Infrastructure\Eloquent;

use App\Modules\Admin\Application\Dto\Page;
use App\Modules\Admin\Application\Dto\ReviewRow;
use App\Modules\Admin\Application\Port\AdminReviewReader;
use App\Modules\Admin\Infrastructure\Support\Iso;
use Illuminate\Support\Facades\DB;
use stdClass;

/** Review feed projection. Reads `reviews`, then hydrates term text from `terms` (separate reads). */
final class EloquentAdminReviewReader implements AdminReviewReader
{
    public function list(string $userId, ?string $from, ?string $to, int $page, int $perPage): Page
    {
        $base = DB::table('reviews')->where('user_id', $userId);
        if ($from !== null && $from !== '') {
            $base->where('answered_at', '>=', $from);
        }
        if ($to !== null && $to !== '') {
            $base->where('answered_at', '<=', $to);
        }

        $total = (clone $base)->count();

        $rows = (clone $base)
            ->orderByDesc('answered_at')
            ->offset(max(0, ($page - 1) * $perPage))
            ->limit($perPage)
            ->get(['id', 'term_id', 'exercise_mode', 'grade', 'is_correct', 'is_practice', 'client_seq', 'answered_at']);

        $termIds = array_values(array_unique(array_map(static fn (stdClass $r): string => (string) $r->term_id, $rows->all())));
        $texts = $this->termTexts($termIds);

        $items = array_map(static fn (stdClass $r): ReviewRow => new ReviewRow(
            id: (string) $r->id,
            termId: (string) $r->term_id,
            termText: $texts[(string) $r->term_id] ?? null,
            exerciseMode: $r->exercise_mode !== null ? (string) $r->exercise_mode : null,
            grade: (string) $r->grade,
            isCorrect: $r->is_correct !== null ? (bool) $r->is_correct : null,
            isPractice: (bool) $r->is_practice,
            clientSeq: (int) $r->client_seq,
            answeredAt: Iso::orNull($r->answered_at),
        ), $rows->all());

        return new Page(array_values($items), $total, $page, $perPage);
    }

    /**
     * @param  list<string>  $termIds
     * @return array<string, string>
     */
    private function termTexts(array $termIds): array
    {
        if ($termIds === []) {
            return [];
        }

        /** @var array<string, string> $map */
        $map = DB::table('terms')->whereIn('id', $termIds)->pluck('text', 'id')
            ->map(static fn ($t): string => (string) $t)->all();

        return $map;
    }
}
