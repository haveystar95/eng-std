<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Repository;

use App\Modules\Learning\Domain\Entity\StudySession;

interface StudySessionRepository
{
    public function save(StudySession $session): void;
}
