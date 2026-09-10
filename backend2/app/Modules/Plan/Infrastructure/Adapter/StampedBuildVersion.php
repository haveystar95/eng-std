<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Adapter;

use App\Modules\Plan\Application\Port\BuildVersion;

/**
 * The server's build, the same way `/health` answers it: `APP_COMMIT` from the environment, else
 * the stamp `scripts/stamp-build.sh` writes to `storage/app/commit`, else `unknown` — the container
 * mounts `backend2/` only, so there is no git to ask.
 */
final class StampedBuildVersion implements BuildVersion
{
    private ?string $memo = null;

    public function __construct(private readonly string $stampPath, private readonly string $fromEnv = '') {}

    public function current(): string
    {
        if ($this->memo !== null) {
            return $this->memo;
        }
        $env = trim($this->fromEnv);
        if ($env !== '') {
            return $this->memo = $env;
        }
        $stamp = is_file($this->stampPath) ? trim((string) file_get_contents($this->stampPath)) : '';

        return $this->memo = $stamp !== '' ? $stamp : 'unknown';
    }
}
