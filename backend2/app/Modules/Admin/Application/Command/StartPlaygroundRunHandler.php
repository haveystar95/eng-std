<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Command;

use App\Modules\Generation\Application\Service\PlaygroundRuns;

/**
 * Starts a sandbox run. A pass-through by design: the provider name stays a STRING all the way down, because Generation's
 * `ProviderId` is that module's Domain and a back-office projection may not import it; an unknown provider, a missing key
 * or an unlisted model come back in the run's answer as text, which is where the person running the experiment looks.
 */
final readonly class StartPlaygroundRunHandler
{
    public function __construct(private PlaygroundRuns $runs) {}

    /** @return string the run's id */
    public function __invoke(StartPlaygroundRun $command): string
    {
        return $this->runs->start($command->provider, $command->model, $command->prompt, $command->temperature);
    }
}
