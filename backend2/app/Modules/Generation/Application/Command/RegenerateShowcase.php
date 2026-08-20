<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

/**
 * Re-write the CORE of every term still carrying an old prompt version, and rebuild the machinery on
 * top of it. The sweep the provenance columns were added for.
 *
 * It REPLACES content, which every other writer in this codebase refuses to do — see
 * {@see \App\Modules\Vocabulary\Application\Port\TermCoreWriter} for why that exception is confined
 * to this one caller.
 */
final readonly class RegenerateShowcase
{
    /**
     * @param  list<string>  $promptVersions  which vintages to sweep
     * @param  string|null  $afterId  resume cursor: the last term id a previous pass finished
     * @param  int  $limit  0 = no cap. A first pass is meant to be small and read afterwards.
     * @param  bool  $dryRun  count and price; call nothing, write nothing
     */
    public function __construct(
        public array $promptVersions = ['legacy', 'v8', 'v9'],
        public ?string $afterId = null,
        public int $limit = 0,
        public bool $dryRun = false,
        public string $translationLang = 'ru',
        public bool $withMechanics = true,
    ) {}
}
