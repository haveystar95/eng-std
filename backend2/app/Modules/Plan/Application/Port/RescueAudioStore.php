<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Application\Dto\SpokenAudio;

/**
 * THE SOUND OF THE RESCUE KIT (наряд LANG-1b §2): one file per line of a target's kit said in one learner voice, filed by
 * the key the kit computes from the target, the learner's gender, the voice and the line
 * ({@see \App\Modules\Plan\Application\Service\RescueKits::audioKey()}) — bought once and read by every plan of that
 * target and gender. Not `plan_line_audios`: those rows are a scene's bill (what `plan:speak-backfill` owes a scene,
 * what `plan:speak-report` counts), and the kit belongs to no scene. What a file cost is kept beside it.
 */
interface RescueAudioStore
{
    public function has(string $key): bool;

    /** Writes the bytes and what they cost; a key that has its file already is left as it is. */
    public function put(string $key, SpokenAudio $audio): void;

    /** The file of a key — its bytes and format with what it cost — or null when none was bought. */
    public function read(string $key): ?SpokenAudio;
}
