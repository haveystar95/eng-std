<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Generation\Application\Dto\TermReadingBrief;
use App\Modules\Generation\Application\Dto\TermReadingResult;
use App\Modules\Generation\Application\Port\TermTransliteratorPort;
use Closure;

/**
 * A scripted reading model that COUNTS. The count is the assertion in half these tests: «уже есть
 * чтение → платный вызов не делается» is a statement about calls, not about rows, and a double that
 * only returned an answer could not tell the difference between «not asked» and «asked and ignored».
 */
final class CountingTermTransliterator implements TermTransliteratorPort
{
    /** @var list<TermReadingBrief> */
    public array $calls = [];

    /** @param string|Closure(TermReadingBrief): string $answer */
    public function __construct(
        private readonly string|Closure $answer = 'римбёрсмент',
        private readonly string $promptVersion = 'v15.1',
    ) {}

    public function read(TermReadingBrief $brief): TermReadingResult
    {
        $this->calls[] = $brief;

        return new TermReadingResult(
            text: $this->answer instanceof Closure ? ($this->answer)($brief) : $this->answer,
            model: 'gpt-5.4-fake',
            promptVersion: $this->promptVersion,
            tokensIn: 900,
            tokensOut: 8,
        );
    }
}
