<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use RuntimeException;

/**
 * THE VOICE VENDOR'S ACCOUNT REFUSED THE CALL (TTS-2): 401 — a wrong key, a key without the permission — or 402 — no
 * credits left, or a voice the plan does not include. Part of the {@see SpeechSynthesizerPort} contract.
 *
 * Not transient, and that is the point of the type: a retry buys the same refusal, and a queue that kept knocking would
 * only fill the log. The job fails with the vendor's own code, the log says why, and the day reads its lines with the
 * phone's voice until the account is fixed and `plan:speak-backfill` runs.
 *
 * `vendorCode` is what the vendor named it (`detail.code`, else `detail.status`): `paid_plan_required` («Free users
 * cannot use library voices via the API», live 15.09), `quota_exceeded`, `insufficient_credits`, `invalid_api_key`,
 * `missing_permissions`.
 */
final class SpeechAccountError extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly string $vendorCode,
        public readonly int $httpStatus,
    ) {
        parent::__construct($message);
    }

    public static function refused(string $provider, int $httpStatus, string $vendorCode, string $detail): self
    {
        return new self("{$provider} speech account refused ({$httpStatus} {$vendorCode}): {$detail}", $vendorCode, $httpStatus);
    }
}
