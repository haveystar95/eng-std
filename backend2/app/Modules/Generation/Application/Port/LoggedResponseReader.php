<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

/**
 * Generation's own seam onto Observability's logged API calls — kept as a Generation-owned port
 * (rather than depending on `Observability\Application\ApiRequestLogReader` directly) because
 * Application layers may only depend on their own module's Domain plus other modules'
 * Application, and this needs an Infrastructure-level bridge either way (see
 * `GenerationInfrastructure`'s allowed edge onto `ObservabilityApplication` in deptrac.yaml).
 */
interface LoggedResponseReader
{
    /** @return array<string, mixed>|null */
    public function findResponseBody(string $logId): ?array;
}
