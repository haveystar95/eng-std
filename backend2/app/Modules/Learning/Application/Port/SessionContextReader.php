<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Learning\Application\Dto\SessionContext;

/**
 * Reads the study sessions an answer batch names: owner, practice flag and the fixed set of term
 * ids each was built with.
 *
 * The composition exists so an answer for a term outside its session can be rejected — otherwise
 * an abandoned session plus a retry could push answers for out-of-context terms and spend the
 * daily quota on words the user never saw. That guard protects SCHEDULING; free practice schedules
 * nothing, which is why an offline practice session may be adopted rather than rejected. The owner
 * is read for a guard that has no exceptions: an answer is never accepted against another user's
 * session.
 */
interface SessionContextReader
{
    /**
     * @param  list<string>  $sessionIds
     * @return array<string, SessionContext>  session id → context; missing ids are simply absent
     */
    public function byIds(array $sessionIds): array;
}
