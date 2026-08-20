<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

/**
 * What calls on this model have ACTUALLY cost in tokens, averaged over the ones this deployment
 * logged — the станок's ledger and the bake-off sandbox.
 *
 * An estimate built only from a price table is an estimate of arithmetic, not of a bill: what
 * decides the bill is how many tokens one call really carries, and that is a fact about this app's
 * prompts and this app's data. It exists as a port because "what did we spend" is a reading, and the
 * layer that decides whether to spend more must not be the layer that knows which tables hold it.
 */
interface ObservedTokenAverages
{
    /**
     * Mean [tokens_in, tokens_out] per call for this model, or null when nothing has been logged on
     * it — a caller must then say so rather than pass a guess off as a measurement.
     *
     * @param  string  $model  as configured; dated vendor snapshots of the same model fold together
     * @param  string|null  $shape  narrow to calls of one prompt shape, when the sandbox recorded it
     * @return array{0: int, 1: int}|null
     */
    public function perCall(string $model, ?string $shape = null): ?array;
}
