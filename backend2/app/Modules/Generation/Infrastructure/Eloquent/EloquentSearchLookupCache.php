<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Eloquent;

use App\Modules\Generation\Application\Dto\CachedLookup;
use App\Modules\Generation\Application\Dto\WordLookupResult;
use App\Modules\Generation\Application\Port\SearchLookupCache;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class EloquentSearchLookupCache implements SearchLookupCache
{
    public function find(string $normalizedQuery, string $lang, string $nativeLang): ?CachedLookup
    {
        $row = DB::table('search_lookups')
            ->where('normalized_query', $normalizedQuery)
            ->where('lang', $lang)
            ->where('native_lang', $nativeLang)
            ->first();

        return $row !== null ? $this->toDto($row) : null;
    }

    public function findById(string $id): ?CachedLookup
    {
        $row = DB::table('search_lookups')->where('id', $id)->first();

        return $row !== null ? $this->toDto($row) : null;
    }

    public function countPaidSince(UserId $userId, DateTimeImmutable $since): int
    {
        return DB::table('search_lookups')
            ->where('user_id', $userId->value)
            ->where('created_at', '>=', $since)
            ->count();
    }

    public function store(
        UserId $payerId,
        string $normalizedQuery,
        string $lang,
        string $nativeLang,
        WordLookupResult $result,
    ): CachedLookup {
        $id = (string) Ulid::generate();

        // insertOrIgnore + re-read: under a race the loser gets the winner's row, which is the right
        // outcome — the two answers are about the same word, and one of them is already the one the
        // cache will serve everybody. Note the loser is NOT charged a quota slot for a row they did
        // not write; countPaidSince counts rows, and theirs does not exist.
        // upsert, not insertOrIgnore: a re-lookup of a word cached by an OLDER prompt has to be
        // able to replace that row, or the stale answer would win forever and the money spent
        // refreshing it would buy nothing. The conflict target is the cache key itself.
        $written = DB::table('search_lookups')->upsert([
            'id' => $id,
            'user_id' => $payerId->value,
            'normalized_query' => $normalizedQuery,
            'lang' => $lang,
            'native_lang' => $nativeLang,
            'payload' => json_encode($result->toPayload(), JSON_UNESCAPED_UNICODE),
            'model' => $result->model,
            'prompt_version' => $result->promptVersion,
            'tokens_in' => $result->tokensIn,
            'tokens_out' => $result->tokensOut,
            'cost_usd' => $result->costUsd,
            'created_at' => now(),
            'updated_at' => now(),
        ], ['normalized_query', 'lang', 'native_lang'], [
            'payload', 'model', 'prompt_version', 'tokens_in', 'tokens_out', 'cost_usd', 'updated_at',
        ]) > 0;

        $stored = $this->find($normalizedQuery, $lang, $nativeLang)
            ?? throw new RuntimeException("The lookup for «{$normalizedQuery}» vanished right after it was written.");

        // `fresh` is about THIS call having paid, so it follows the insert, not the read.
        return new CachedLookup(
            $stored->id, $stored->normalizedQuery, $stored->lang, $stored->nativeLang, $stored->text,
            $stored->type, $stored->translation, $stored->description, $stored->example,
            $stored->exampleTranslation, $stored->cefr, $stored->transcription,
            $stored->imageApiPrompt, $stored->model, $stored->promptVersion, $stored->createdAt,
            fresh: $written, illustrationDecided: $stored->illustrationDecided,
            notRecognized: $stored->notRecognized,
        );
    }

    private function toDto(\stdClass $row): CachedLookup
    {
        $payload = json_decode((string) $row->payload, true);
        $payload = is_array($payload) ? $payload : [];

        return new CachedLookup(
            id: (string) $row->id,
            normalizedQuery: (string) $row->normalized_query,
            lang: (string) $row->lang,
            nativeLang: (string) $row->native_lang,
            text: $this->str($payload, 'text') ?? (string) $row->normalized_query,
            type: $this->str($payload, 'type') ?? 'word',
            translation: $this->str($payload, 'translation') ?? '',
            description: $this->str($payload, 'description') ?? '',
            example: $this->str($payload, 'example'),
            exampleTranslation: $this->str($payload, 'example_translation'),
            cefr: $this->str($payload, 'cefr'),
            transcription: $this->str($payload, 'transcription'),
            imageApiPrompt: $this->str($payload, 'image_api_prompt'),
            model: (string) $row->model,
            promptVersion: (string) $row->prompt_version,
            createdAt: new DateTimeImmutable((string) $row->created_at),
            // The KEY, not the value: an empty query is a decision, a missing key is a row from
            // before the question was asked.
            illustrationDecided: array_key_exists('image_api_prompt', $payload),
            // A refusal row carries this and nothing else — every field above degraded to its
            // fallback, and none of them is ever read for such a row.
            notRecognized: ($payload['not_recognized'] ?? false) === true,
        );
    }

    /** @param array<mixed> $payload */
    private function str(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
