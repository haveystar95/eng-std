<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\ValueObject;

/** A realtime practice dialog. The client generates it (ULID) so a start is idempotent. */
final class PracticeDialogId extends Identifier {}
