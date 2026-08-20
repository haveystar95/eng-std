<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

/** One term's content passport: what it holds, and what each trainer can do with it. */
final readonly class GetTermContentPassport
{
    public function __construct(public string $termId) {}
}
