<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Eloquent;

use App\Modules\Generation\Application\Port\GenerationAccountEraser;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\DB;

final class EloquentGenerationAccountEraser implements GenerationAccountEraser
{
    public function eraseFor(UserId $userId): void
    {
        DB::table('generation_requests')->where('user_id', $userId->value)->delete();
    }
}
