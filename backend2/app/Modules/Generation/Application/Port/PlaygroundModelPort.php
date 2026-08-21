<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Generation\Application\Dto\PlaygroundRawReply;
use App\Modules\Generation\Domain\ValueObject\ProviderId;

/**
 * One vendor, asked one question, RAW.
 *
 * The narrow sibling of {@see ContentModelPort}, and narrow in the one direction that matters: there
 * is no schema and no system prompt. The sandbox exists so a person can send the exact text they
 * typed and see the exact text that comes back — wrapping it in this app's prompt scaffolding, or
 * demanding a JSON schema, would make the experiment measure the scaffolding.
 *
 * Nothing production reaches this port: generation and the станок go through `ContentModelPort` and
 * its adapters, which are unchanged. This is a second, smaller seam beside them rather than a
 * loosening of theirs — a `complete()` that sometimes enforces a schema and sometimes does not is
 * how the production path would eventually be called without one.
 *
 * Failure throws. The caller (one Application service) turns it into text for the screen, because
 * «нет кредитов» is an answer a sandbox should print, not a 500.
 */
interface PlaygroundModelPort
{
    public function provider(): ProviderId;

    public function model(): string;

    /**
     * @param  string  $prompt  the operator's text, verbatim — no template, no system message
     * @param  float|null  $temperature  omitted from the request entirely when null: several current
     *        models accept only their default and REJECT the parameter, so sending a value nobody
     *        asked for turns a working model into an error
     */
    public function ask(string $prompt, ?float $temperature = null): PlaygroundRawReply;
}
