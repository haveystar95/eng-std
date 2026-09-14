<?php

declare(strict_types=1);

/*
 * PcmTurnCutter against a sound whose line starts are known (DAY-UI-3).
 *
 *   docker compose exec -T app php docs/research/day-ui-3/tools/cut_check.php storage/app/day-ui-3/dialogue
 *
 * Reads `<base>.pcm` + `<base>.json` (texts, rate, and — for a synthetic sound — `starts_ms`) and prints
 * where every line was cut, beside where it really starts. A real vendor sound has no `starts_ms`:
 * then only the cut is printed, and `--wav` writes every piece next to the sound to listen to.
 */

use App\Modules\Generation\Infrastructure\Adapter\PcmTurnCutter;

require __DIR__.'/../../../../vendor/autoload.php';

$base = $argv[1] ?? '';
$pcm = (string) file_get_contents($base.'.pcm');
$meta = json_decode((string) file_get_contents($base.'.json'), true);
$texts = $meta['texts'];
$rate = (int) $meta['rate'];
$starts = $meta['starts_ms'] ?? null;

$cutter = new PcmTurnCutter();
$started = microtime(true);
$spans = $cutter->spans($pcm, $rate, $texts);
printf("cut %d lines of %.1f s in %d ms\n", count($spans), strlen($pcm) / 2 / $rate, (microtime(true) - $started) * 1000);

$worst = 0;
foreach ($spans as $i => [$from, $to]) {
    $truth = $starts[$i] ?? null;
    $off = $truth === null ? null : $from - $truth;
    $worst = max($worst, abs($off ?? 0));
    printf("%2d  %6d–%6d ms%s  %s\n", $i, $from, $to, $off === null ? '' : sprintf('  (start %+5d ms)', $off), $texts[$i]);
    if (in_array('--wav', $argv, true)) {
        $piece = substr($pcm, intdiv($from * $rate, 1000) * 2, intdiv(($to - $from) * $rate, 1000) * 2);
        $header = 'RIFF'.pack('V', 36 + strlen($piece)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, $rate, $rate * 2, 2, 16).'data'.pack('V', strlen($piece));
        file_put_contents(sprintf('%s-%02d.wav', $base, $i), $header.$piece);
    }
}
if ($starts !== null) {
    printf("worst start offset: %d ms\n", $worst);
}
