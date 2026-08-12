<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Generation\Application\Dto\RepairedTranslation;
use App\Modules\Generation\Application\Dto\TranslationRepairBrief;
use App\Modules\Generation\Application\Port\TranslationRepairPort;
use RuntimeException;

/**
 * A repairer whose answers are written in advance, one per call, so a test can say exactly what the
 * model does on attempt 1, attempt 2, … — which is the only way to distinguish "the retry worked"
 * from "the retry was never made".
 *
 * A `null` in the script means the call THROWS (a transport failure); the string is used as the
 * translation and, with a sentence in the brief, as the example translation too. Running past the
 * end of the script throws, so a test that expected two calls and got three fails loudly instead of
 * silently passing on a repeated last answer.
 */
final class ScriptedTranslationRepairer implements TranslationRepairPort
{
    public int $calls = 0;

    /** @var list<TranslationRepairBrief> every brief it was handed, for asserting what was asked */
    public array $briefs = [];

    /** @param list<string|null> $answers */
    public function __construct(private readonly array $answers) {}

    public function repair(TranslationRepairBrief $brief): RepairedTranslation
    {
        $this->briefs[] = $brief;
        // array_key_exists, not ??: a scripted `null` MEANS "this call throws", and `??` cannot tell
        // that apart from running off the end of the script.
        if (! array_key_exists($this->calls, $this->answers)) {
            throw new RuntimeException('repairer called more times than scripted');
        }
        $answer = $this->answers[$this->calls];
        $this->calls++;

        if ($answer === null) {
            throw new RuntimeException('simulated OpenAI failure');
        }

        return new RepairedTranslation(
            translation: $answer,
            exampleTranslation: $brief->sentence !== null ? $answer . ' (пример)' : null,
            model: 'gpt-4o-mini',
            tokensIn: 30,
            tokensOut: 15,
        );
    }
}
