<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * WHERE A CONSTRUCTION OF THE TALK STANDS (наряд FIX-4 §2): not said yet, said ALMOST — its words with one of them
 * different, added or left out, which does not close it — or said. A said one stays said; an almost one may yet be said.
 */
enum FrameState: string
{
    case None = 'none';
    case Almost = 'almost';
    case Said = 'said';
}
