<?php

namespace App\Jobs;

use App\Models\AiJob;
use App\Models\Collection;
use App\Models\User;
use App\Services\Ai\AiProvider;
use App\Services\Vocabulary;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class GenerateCollectionJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    private const CEFR_ORDER = ['A1' => 1, 'A2' => 2, 'B1' => 3, 'B2' => 4, 'C1' => 5, 'C2' => 6];

    public function __construct(public string $aiJobId) {}

    public function handle(AiProvider $ai, Vocabulary $vocab): void
    {
        $job = AiJob::find($this->aiJobId);
        if (! $job || $job->status === 'done') {
            return;
        }

        $job->update(['status' => 'processing']);

        $payload = $job->payload ?? [];
        $topic = $payload['topic'] ?? 'General vocabulary';
        $levels = $payload['levels'] ?? ['B1'];
        $size = (int) ($payload['size'] ?? 15);

        $words = $this->filterByLevel($ai->generateWords($topic, $levels, $size), $levels);

        if (empty($words)) {
            $job->update(['status' => 'failed', 'error' => 'AI returned no usable words.']);

            return;
        }

        DB::transaction(function () use ($job, $topic, $words, $vocab) {
            $user = User::find($job->user_id);
            $collection = Collection::create([
                'user_id' => $user->id,
                'title' => ucfirst($topic),
                'emoji' => '✨',
                'topic' => $topic,
                'source' => 'ai',
            ]);

            foreach ($words as $w) {
                if (empty($w['term']) || empty($w['translation'])) {
                    continue;
                }
                $vocab->addToCollection($user, $collection, [
                    'term' => $w['term'],
                    'translation' => $w['translation'],
                    'transcription' => $w['transcription'] ?? null,
                    'example' => $w['example'] ?? null,
                    'cefr_level' => $w['cefr_level'] ?? null,
                ]);
            }

            $job->update(['status' => 'done', 'collection_id' => $collection->id]);
        });
    }

    /**
     * Drop items whose self-reported CEFR level falls outside the requested
     * range (e.g. easy A-level words when C1 was requested). If filtering would
     * leave too few, keep everything rather than fail the user.
     *
     * @param array<int, array<string, mixed>> $words
     * @param string[] $levels
     * @return array<int, array<string, mixed>>
     */
    private function filterByLevel(array $words, array $levels): array
    {
        $ranks = array_map(fn ($l) => self::CEFR_ORDER[$l] ?? 0, $levels);
        $ranks = array_filter($ranks);
        if (empty($ranks)) {
            return $words;
        }
        $min = min($ranks);
        $max = max($ranks);

        $kept = array_values(array_filter($words, function ($w) use ($min, $max) {
            $r = self::CEFR_ORDER[$w['cefr_level'] ?? ''] ?? null;
            return $r === null || ($r >= $min && $r <= $max);
        }));

        return count($kept) >= max(1, (int) floor(count($words) * 0.4)) ? $kept : $words;
    }

    public function failed(Throwable $e): void
    {
        AiJob::where('id', $this->aiJobId)->update([
            'status' => 'failed',
            'error' => $e->getMessage(),
        ]);
    }
}
