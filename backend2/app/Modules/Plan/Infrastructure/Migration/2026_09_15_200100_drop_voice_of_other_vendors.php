<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * ONE VOICE IN THE PRODUCT (наряд TTS-2; owner: «аудио, уже озвученное прежним вендором, — удалить и переозвучить»).
     *
     * The server's voice is ElevenLabs now, and every file another vendor said is gone — its row in
     * `plan_line_audios` and its bytes on `plan.audio_disk`. Nothing reads those rows any more: a reader looks a line up
     * under the voice key of the pack's current voice, so an old file would never be played, only stored. The lines
     * they said become owed again, and `plan:speak-backfill` buys them in the one voice. A fresh database has none, and
     * it is a no-op there.
     *
     * No `down()`: the bytes are gone with the rows; the way back is the database backup taken before `migrate`
     * (`scripts/db-backup.sh`) — and a voice nobody reads.
     */
    public function up(): void
    {
        $rows = DB::table('plan_line_audios')->where('voice_key', 'not like', 'elevenlabs:%')->get(['id', 'path', 'bytes']);
        $disk = Storage::disk((string) config('plan.audio_disk', 'local'));
        $files = 0;
        foreach ($rows as $row) {
            if ($disk->exists((string) $row->path)) {
                $disk->delete((string) $row->path);
                $files++;
            }
        }
        foreach (array_chunk($rows->pluck('id')->all(), 500) as $chunk) {
            DB::table('plan_line_audios')->whereIn('id', $chunk)->delete();
        }

        Log::info('plan: voice of other vendors dropped', ['rows' => $rows->count(), 'files' => $files, 'bytes' => (int) $rows->sum('bytes')]);
    }

    public function down(): void
    {
        // One-way by design — see the class docblock.
    }
};
