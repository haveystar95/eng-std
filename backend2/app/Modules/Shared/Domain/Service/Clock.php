<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Service;

use DateTimeImmutable;

/** Domain time source. Never call now()/Carbon in the Domain — inject this. */
interface Clock
{
    public function now(): DateTimeImmutable;
}
