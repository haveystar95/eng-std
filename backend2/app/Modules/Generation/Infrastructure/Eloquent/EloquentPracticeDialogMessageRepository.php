<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Eloquent;

use App\Modules\Generation\Domain\Repository\PracticeDialogMessageRepository;
use App\Modules\Generation\Domain\ValueObject\TranscriptLine;
use App\Modules\Generation\Domain\ValueObject\TranscriptRole;
use App\Modules\Shared\Domain\ValueObject\PracticeDialogId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Support\Facades\DB;

final class EloquentPracticeDialogMessageRepository implements PracticeDialogMessageRepository
{
    public function append(PracticeDialogId $dialogId, array $lines): void
    {
        if ($lines === []) {
            return;
        }

        $now = now();
        $rows = [];
        foreach ($lines as $line) {
            $rows[] = [
                'id' => Ulid::generate(),
                'dialog_id' => $dialogId->value,
                'role' => $line->role->value,
                'text' => $line->text,
                'ts' => $line->ts,
                'created_at' => $now,
            ];
        }

        // Idempotent by the unique (dialog_id, role, ts): a re-uploaded batch inserts only new lines.
        DB::table('practice_dialog_messages')->insertOrIgnore($rows);
    }

    /** @return list<TranscriptLine> */
    public function forDialog(PracticeDialogId $dialogId): array
    {
        $rows = DB::table('practice_dialog_messages')
            ->where('dialog_id', $dialogId->value)
            ->orderBy('ts')
            ->orderBy('role')
            ->get(['role', 'text', 'ts']);

        $lines = [];
        foreach ($rows as $row) {
            $lines[] = new TranscriptLine(
                role: TranscriptRole::from((string) $row->role),
                text: (string) $row->text,
                ts: (int) $row->ts,
            );
        }

        return $lines;
    }
}
