<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Eloquent;

use App\Modules\Generation\Application\Dto\PlanSpend;
use App\Modules\Generation\Application\Port\RecordsPlanSpend;
use App\Modules\Generation\Domain\Exception\PlanSpendNotRecorded;
use App\Modules\Generation\Domain\Service\PromptNormalizer;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A plan's spend, into `generation_requests` with `purpose = 'plan'`.
 *
 * ## It shouts and then it throws
 *
 * The one writer in this module that does neither of the two things every other writer here does:
 * it does not catch, and it does not degrade. A refused row means a paid call has no record, and
 * the failure mode that produced this class was precisely a refusal being caught one layer down
 * and dropped. The log line is for the person reading at 8am; the exception is so that there IS a
 * person reading at 8am.
 *
 * `Log::error` FIRST and then the throw, in that order and not the other one: if the throw is
 * caught somewhere unexpected on the way up, the line is already written.
 *
 * ## `normalized_prompt` is written and is not a cache key
 *
 * The column is NOT NULL and the prompt cache reads it, so a plan row could otherwise be handed
 * back to a collection generation that happened to ask about the same words. The cache query
 * excludes `purpose <> 'generation'` ({@see EloquentGenerationRequestRepository::findCacheableCollection});
 * this writes a value that is honest rather than empty, and the two together mean a plan row can
 * be read by a human and never by the cache.
 */
final readonly class EloquentPlanSpendLedger implements RecordsPlanSpend
{
    public function __construct(private PromptNormalizer $normalizer = new PromptNormalizer()) {}

    public function record(PlanSpend $spend): void
    {
        $subject = trim($spend->subject) !== '' ? trim($spend->subject) : 'план';

        try {
            DB::table('generation_requests')->insert([
                'id' => Ulid::generate(),
                'user_id' => $spend->userId,
                'purpose' => 'plan',
                'plan_id' => $spend->planId,
                // «Что просили» for a human: the goal for an outline, «день N — заголовок» for a day.
                'prompt' => $spend->call . ': ' . $subject,
                'normalized_prompt' => $this->normalizer->normalize($subject),
                'source_lang' => $spend->supportLang,
                'target_lang' => $spend->targetLang,
                // A plan has a LEVEL, not CEFR levels. The column is the collection generator's
                // and has nothing to say here; an empty list is the honest value rather than a
                // level invented to fill it.
                'levels' => json_encode([], JSON_THROW_ON_ERROR),
                'size' => $spend->size,
                'prompt_version' => $spend->promptVersion,
                'status' => $spend->succeeded ? 'succeeded' : 'failed',
                'model' => $spend->model,
                'tokens_in' => $spend->tokensIn,
                'tokens_out' => $spend->tokensOut,
                'cost_usd' => $spend->costUsd,
                'error' => $spend->error,
                'created_at' => now(),
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('plan spend was NOT recorded — a paid call has no ledger row', [
                'plan_id' => $spend->planId,
                'user_id' => $spend->userId,
                'call' => $spend->call,
                'model' => $spend->model,
                'tokens_in' => $spend->tokensIn,
                'tokens_out' => $spend->tokensOut,
                'cost_usd' => $spend->costUsd,
                'error' => $e->getMessage(),
            ]);

            throw PlanSpendNotRecorded::forPlan($spend->planId, $spend->costUsd, $e);
        }
    }
}
