<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Account deletion (наряд ACC-1 §1): every plan of the learner — a deleted one too — with everything that hangs on it
 * (scenes, days, cards, terms, the voice rows, the talks and their lines, the walked stages, the journal, the letters)
 * and the files of it on the disks: the spoken lines of its scenes, the sound of its talks, the square copies of its
 * scene photos. Called inside the account's transaction; the files go only once that transaction has committed — a
 * deletion rolled back keeps them.
 */
interface PlanAccountEraser
{
    /** @return int how many plans the learner had — the one number the account's deletion keeps */
    public function eraseFor(UserId $userId): int;
}
