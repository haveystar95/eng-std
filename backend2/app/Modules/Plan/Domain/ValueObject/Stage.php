<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/** The five stages of a day, in the order they are walked. */
enum Stage: string
{
    case Words = 'words';
    case Phrases = 'phrases';
    case Dialogue = 'dialogue';
    case Listen = 'listen';
    case Speak = 'speak';

    /** @return list<self> */
    public static function ordered(): array
    {
        return [self::Words, self::Phrases, self::Dialogue, self::Listen, self::Speak];
    }

    public function position(): int
    {
        return (int) array_search($this, self::ordered(), true);
    }
}
