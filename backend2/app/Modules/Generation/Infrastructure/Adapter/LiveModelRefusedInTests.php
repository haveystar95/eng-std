<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use RuntimeException;

/**
 * A live vendor adapter was about to be built while `APP_ENV=testing`.
 *
 * Not a `ProblemDetails` and deliberately not catchable-into-a-state: it is a developer error, it
 * must stop the test rather than turn into a `fail_reason` somebody reads next week, and its whole
 * job is to say the fix in the message.
 */
final class LiveModelRefusedInTests extends RuntimeException
{
    public static function for(string $what): self
    {
        return new self(
            "Refusing to build the live «{$what}» adapter under APP_ENV=testing — use fake driver in tests. "
            . 'Either leave GENERATION_DRIVER=fake (phpunit.xml sets it), or, if this test is ABOUT '
            . 'the real adapter and has Http::fake() underneath it, opt in explicitly with '
            . '`allowLiveAdapters()` (tests/Pest.php).'
        );
    }
}
