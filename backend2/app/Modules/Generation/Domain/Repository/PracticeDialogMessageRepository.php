<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Repository;

use App\Modules\Generation\Domain\ValueObject\TranscriptLine;
use App\Modules\Shared\Domain\ValueObject\PracticeDialogId;

/**
 * Append-only transcript store. Appending is idempotent on (dialog_id, role, ts): re-uploading a
 * batch inserts only the lines not already present, so the client can retry a POST safely.
 */
interface PracticeDialogMessageRepository
{
    /** @param list<TranscriptLine> $lines */
    public function append(PracticeDialogId $dialogId, array $lines): void;

    /**
     * All stored lines for the dialog, in (ts, role) order.
     *
     * @return list<TranscriptLine>
     */
    public function forDialog(PracticeDialogId $dialogId): array;
}
