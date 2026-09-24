<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use Illuminate\Http\Client\Response;

/**
 * ONE CALL TO A VENDOR, SENT AND JOURNALED ({@see VendorCall::send()}): the vendor's response, and the row of the journal
 * of model calls it was written under (`model_calls.id`) — null when the journal could not write it. A caller that keeps
 * something of the call beside its answer (the talk's refusals, наряд FIX-4 §6) names the call by this id.
 */
final readonly class SentCall
{
    public function __construct(
        public Response $response,
        public ?string $callId,
    ) {}
}
