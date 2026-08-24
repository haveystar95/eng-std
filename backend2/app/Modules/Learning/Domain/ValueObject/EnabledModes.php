<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

use App\Modules\Shared\Domain\Service\LanguageModeSupport;
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

    /**
     * THE intersection: the product matrix ∧ what this language can carry.
     *
     * One function, and it lives on this value object because this object IS «what the product has
     * switched on» — narrowing it by the language of the card being dealt is the same kind of
     * statement, and putting the intersection anywhere else would mean two places could disagree
     * about which trainers exist for a card (DECISIONS п. 130). It is applied PER CARD, not once per
     * session: a session legitimately mixes collections of different pairs, and the Polish word and
     * the English one next to it do not have the same trainers (п. 143).
     *
     * The direction is one-way and cannot be inverted here: this can only REMOVE modes. The panel
     * closes a trainer; it never opens one for a language that cannot carry it.
     *
     * Two ways the intersection can come out empty, and they are NOT the same answer:
     *
     *  - the language carries NOTHING (`zh`, `ja` — reference languages in v1, пп. 84, 136). Null,
     *    and the caller deals no card. There is no honest trainer to fall back to.
     *  - the language carries trainers, but none of the ones currently switched on. That is a
     *    configuration, not a capability, and it gets the same answer configuration always gets
     *    here: the FLOOR — `multiple_choice`, even when it is switched off. The reasoning is
     *    {@see \App\Modules\Learning\Domain\Service\ExerciseSelector::floor()}'s, unchanged: an
     *    empty session is a worse answer to a misconfigured toggle than an unexpected exercise.
     */
    public function forLanguage(string $lang): ?self
    {
        $supported = LanguageModeSupport::modesFor($lang);
        $kept = array_values(array_filter(
            $this->modes,
            static fn (ExerciseMode $mode): bool => in_array($mode->value, $supported, true),
        ));
        if ($kept !== []) {
            return new self($kept);
        }

        if ($supported === []) {
            return null;
        }

        $floor = in_array(ExerciseMode::MultipleChoice->value, $supported, true)
            ? ExerciseMode::MultipleChoice
            : ExerciseMode::from($supported[0]);

        return new self([$floor]);
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
