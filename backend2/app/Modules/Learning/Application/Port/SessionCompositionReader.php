<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

/**
 * The fixed set of term ids each study session was built with. An answer that names a session
 * must be for a term in that session — otherwise an abandoned session plus a retry could push
 * answers for out-of-context terms and spend the daily quota on words the user never saw.
 */
interface SessionCompositionReader
{
    /**
     * @param  list<string>  $sessionIds
     * @return array<string, array<string, true>>  session id → set of its term ids
     */
    public function compositionsByIds(array $sessionIds): array;
}
