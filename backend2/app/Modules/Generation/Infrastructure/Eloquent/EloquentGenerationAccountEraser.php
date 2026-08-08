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
        // Practice-dialog transcripts are PII (the user's own speech) and must go with the account
        // (device-batch F23 — they survived deletion). Messages have no cascading FK, so drop them
        // first, by the user's dialog ids, then the dialogs themselves.
        DB::table('practice_dialog_messages')
            ->whereIn('dialog_id', DB::table('practice_dialogs')->where('user_id', $userId->value)->select('id'))
            ->delete();
        DB::table('practice_dialogs')->where('user_id', $userId->value)->delete();

        DB::table('example_regenerations')->where('user_id', $userId->value)->delete();
        DB::table('generation_requests')->where('user_id', $userId->value)->delete();
    }
}
