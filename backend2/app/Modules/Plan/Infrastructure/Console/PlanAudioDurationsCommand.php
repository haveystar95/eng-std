<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Console;

use App\Modules\Plan\Application\Port\LineAudioStore;
use App\Modules\Shared\Domain\Service\SpeechCost;
use Illuminate\Console\Command;

/**
 * THE LENGTH OF EVERY STORED VOICE FILE (наряд SESSION-1a, разд. 5): `plan_line_audios.duration_ms` filled where it is
 * null, from the stored file — idempotent, buys nothing, prints «без длительности: было N / стало M»; `--dry` counts.
 *
 * The length is the writer's own estimate, {@see SpeechCost::mp3DurationMs} over the file's bytes: the voice is bought
 * as mp3 at 128 kbit/s, and neither getID3 nor ffprobe is in the containers — no decoder, no new package (SESSION-1a
 * D-24). So only an mp3 is measured; a row of another format, a file gone from the disk or an empty one keeps its null
 * — a guessed length would lie to the player — and is counted in «стало».
 *
 * Writes `plan_line_audios.duration_ms` only — take the database backup first, as for any write to the dev database.
 */
final class PlanAudioDurationsCommand extends Command
{
    protected $signature = 'plan:audio-durations {--dry : count the files without a duration, write nothing}';

    protected $description = 'Fill plan_line_audios.duration_ms for stored voice files that have none';

    /** The format the estimate holds for — the format the voice is bought in. */
    private const MEASURED_FORMAT = 'mp3';

    public function handle(LineAudioStore $store): int
    {
        $dry = $this->option('dry') === true;
        $rows = $store->withoutDuration();
        $before = count($rows);

        $filled = 0;
        $notMp3 = 0;
        $noFile = 0;
        foreach ($rows as $row) {
            if ($row->format !== self::MEASURED_FORMAT) {
                $notMp3++;

                continue;
            }
            $bytes = $store->read($row);
            if ($bytes === null || $bytes === '') {
                $noFile++;

                continue;
            }
            if (! $dry) {
                $store->setDuration($row->id, SpeechCost::mp3DurationMs(strlen($bytes)));
            }
            $filled++;
        }

        $after = $dry ? $before : count($store->withoutDuration());
        $this->info("без длительности: было {$before} / стало {$after}");
        if ($noFile > 0) {
            $this->warn("файла нет или он пуст — длительность не записана: {$noFile}");
        }
        if ($notMp3 > 0) {
            $this->warn("не mp3 — длительность по весу не оценивается: {$notMp3}");
        }
        if ($dry) {
            $this->info("--dry: ничего не записано; длительность нашлась бы у {$filled}");
        }

        return self::SUCCESS;
    }
}
