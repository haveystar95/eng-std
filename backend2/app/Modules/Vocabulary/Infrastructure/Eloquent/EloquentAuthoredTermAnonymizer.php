<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Vocabulary\Application\Port\AuthoredTermAnonymizer;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\DB;

final class EloquentAuthoredTermAnonymizer implements AuthoredTermAnonymizer
{
    public function anonymizeAuthor(UserId $userId): void
    {
        // Terms are global and shared — never delete them; just drop the owner link.
        DB::table('terms')->where('created_by', $userId->value)->update(['created_by' => null]);
    }
}
