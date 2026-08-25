<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Shared\Domain\ValueObject\TermId;

/**
 * «This word could use a reading hint» — asked off the request thread, always.
 *
 * The queue is not an optimisation here, it is the isolation: the hint is bought from the STRONG
 * model, and a card must not wait on that call, nor fail with it. A learner who taps «Собрать
 * карточку» gets the card; the hint arrives the way the photo does.
 */
interface DispatchesTermReading
{
    public function writeReading(TermId $termId, string $supportLang): void;
}
