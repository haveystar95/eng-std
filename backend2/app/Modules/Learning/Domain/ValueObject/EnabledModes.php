<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

use InvalidArgumentException;

/**
 * The exercise modes currently switched on (from config, not hard-coded in the selector).
 * Phase 1 ships multiple_choice, word_bank and typing; listening and cloze arrive with TTS and
 * good examples. The selector rotates and falls back only within this set, so it can never
 * hand out a mode that does not exist yet.
 */
final readonly class EnabledModes
{
    /** @var list<ExerciseMode> */
    public array $modes;

    /** @param list<ExerciseMode> $modes */
    public function __construct(array $modes)
    {
        $unique = [];
        foreach ($modes as $mode) {
            if (! in_array($mode, $unique, true)) {
                $unique[] = $mode;
            }
        }
        if ($unique === []) {
            throw new InvalidArgumentException('At least one exercise mode must be enabled.');
        }
        $this->modes = $unique;
    }

    public function has(ExerciseMode $mode): bool
    {
        return in_array($mode, $this->modes, true);
    }

    /**
     * The given modes that are enabled, in the given order (for a stable rotation set).
     *
     * @param  list<ExerciseMode>  $preferred
     * @return list<ExerciseMode>
     */
    public function only(array $preferred): array
    {
        return array_values(array_filter($preferred, fn (ExerciseMode $mode): bool => $this->has($mode)));
    }

    public function first(): ExerciseMode
    {
        return $this->modes[0];
    }
}
