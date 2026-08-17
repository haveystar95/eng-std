<?php

declare(strict_types=1);

namespace App\Modules\Observability\Infrastructure\Eloquent;

use App\Modules\Observability\Application\Port\RequestLogAnonymizer;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\DB;

final class EloquentRequestLogAnonymizer implements RequestLogAnonymizer
{
    public function anonymizeUser(UserId $userId): void
    {
        DB::table('api_request_logs')->where('user_id', $userId->value)->update(['user_id' => null]);
    }
}
