<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/** «Шит» — the day's words and phrases as a list to read. */
final readonly class SheetView
{
    /**
     * @param  list<TermView>  $words
     * @param  list<TermView>  $phrases
     */
    public function __construct(
        public string $planId,
        public int $number,
        public array $words,
        public array $phrases,
    ) {}
}
