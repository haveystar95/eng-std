<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

/** Which build of the server is answering — stamped on every plan and lesson, shown by the API. */
interface BuildVersion
{
    public function current(): string;
}
