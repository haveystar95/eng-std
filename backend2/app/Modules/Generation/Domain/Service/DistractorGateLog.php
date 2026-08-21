<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

use App\Modules\Generation\Domain\ValueObject\DistractorGate;

/**
 * A notepad the validator writes one word on per row: which check decided it.
 *
 * THE POINT IS WHAT IT IS NOT. It takes no decisions, returns nothing to its caller, and is null by
 * default — {@see EnrichmentValidator::validate()} runs identically whether or not one is passed, and
 * the parity test in `tests/Feature/Admin/AdminPlaygroundTest.php` holds it to that by comparing a
 * recorded run against an unrecorded one on the same input.
 *
 * It exists because the alternative was worse. The gates are `continue` statements inside one loop —
 * there is no collaborator to decorate — so the choices were: re-implement the conditions somewhere
 * else (a second copy of the rules, stale the day one moves), or let the loop say which line it took.
 * Re-implementing them would have made the sandbox's «почему выбросило» a plausible-looking guess,
 * which is the one thing a debugging tool must never be.
 *
 * Keyed by the row's index in the batch as handed to the validator, so a caller can line the reasons
 * up with its own input without matching on text.
 */
final class DistractorGateLog
{
    /** @var array<int, DistractorGate> */
    private array $gates = [];

    /** First write wins: a row is decided once, and a later note would be a bug, not an update. */
    public function record(int $index, DistractorGate $gate): void
    {
        $this->gates[$index] ??= $gate;
    }

    /** Every row of the batch that was never touched — the loop stopped at the cap before reaching it. */
    public function fillUnexamined(int $total, DistractorGate $gate = DistractorGate::CapReached): void
    {
        for ($i = 0; $i < $total; $i++) {
            $this->gates[$i] ??= $gate;
        }
    }

    public function gateFor(int $index): ?DistractorGate
    {
        return $this->gates[$index] ?? null;
    }

    /** @return array<int, DistractorGate> */
    public function all(): array
    {
        return $this->gates;
    }
}
