<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * The mode of every check, by name — configuration handed to the Domain. A check not named here
 * runs in `observe`, which is also what every check ships as.
 */
final readonly class CheckModes
{
    /** @param array<string, CheckMode> $modes */
    public function __construct(private array $modes = []) {}

    /** @param array<string, string> $raw check name → mode value, as read from config */
    public static function fromArray(array $raw): self
    {
        $modes = [];
        foreach ($raw as $check => $mode) {
            $modes[$check] = CheckMode::tryFrom($mode) ?? CheckMode::Observe;
        }

        return new self($modes);
    }

    public static function allObserve(): self
    {
        return new self;
    }

    public function for(string $check): CheckMode
    {
        return $this->modes[$check] ?? CheckMode::Observe;
    }
}
