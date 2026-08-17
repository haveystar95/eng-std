<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/**
 * An item the language barrier refused to write, after spending its repair budget on it.
 *
 * It carries the item's TEXT rather than a term id because no term was created — that is the whole
 * point of a barrier as opposed to a finding. `attempts` is how many repair calls were paid for
 * before giving up, so the log answers "was this expensive?" as well as "what went wrong?".
 */
final readonly class RejectedItem
{
    public function __construct(
        public string $text,
        public string $field,       // translation | example_translation | text | example
        public string $reason,
        public int $attempts,
    ) {}
}
