<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Port\SpeechNotCut;

/**
 * ОДИН ЗВУК → СТРОКИ СЦЕНАРИЯ ПО ПАУЗАМ (DAY-UI-3).
 *
 * Вендор отвечает на сценарий одним куском PCM и таймкодов строк не называет. Строки разделены
 * тишиной, но тишина бывает и ВНУТРИ строки (запятая, точка между двумя предложениями реплики
 * собеседника), поэтому «самые длинные паузы» режут не там. Разрез выбирается как расстановка N − 1
 * стыков по паузам, в порядке, с наименьшей ценой: пауза далеко от места, где стык ОЖИДАЕТСЯ по длине
 * текста строк, стоит дорого; длинная пауза — дёшево. Динамика по паузам, O(строк × пауз).
 *
 * Проверка после разреза: доля звука каждого куска не дальше чем втрое от доли его текста. Не
 * сошлось — {@see SpeechNotCut}, и строки остаются голосом телефона: неверно разрезанный звук хуже
 * отсутствующего — он играет чужую строку.
 *
 * Чистый класс над байтами: 16 бит со знаком, little-endian, моно.
 */
final class PcmTurnCutter
{
    private const FRAME_MS = 10;

    /** Символов на строку сверх текста: у короткой строки тоже есть вдох и затухание. */
    private const WEIGHT_PAD = 6;

    public function __construct(
        private readonly int $minGapMs = 160,
        private readonly int $keepEdgeMs = 90,
        private readonly float $positionTolerance = 0.10,
        private readonly float $maxShareRatio = 3.0,
    ) {}

    /**
     * @param  list<string>  $texts  the script's lines, in order
     * @return list<string> PCM of every line, in order
     *
     * @throws SpeechNotCut
     */
    public function cut(string $pcm, int $sampleRate, array $texts): array
    {
        $frameBytes = intdiv($sampleRate * self::FRAME_MS, 1000) * 2;

        return array_map(
            static fn (array $span): string => substr($pcm, intdiv($span[0], self::FRAME_MS) * $frameBytes, intdiv($span[1] - $span[0], self::FRAME_MS) * $frameBytes),
            $this->spans($pcm, $sampleRate, $texts),
        );
    }

    /**
     * Where every line sounds, in milliseconds from the start: [from, to), a little silence kept on
     * both sides and never past the middle of the pause it was cut in.
     *
     * @param  list<string>  $texts
     * @return list<array{0: int, 1: int}>
     *
     * @throws SpeechNotCut
     */
    public function spans(string $pcm, int $sampleRate, array $texts): array
    {
        $lines = count($texts);
        $frameBytes = intdiv($sampleRate * self::FRAME_MS, 1000) * 2;
        if ($lines === 0 || $frameBytes <= 0) {
            throw SpeechNotCut::because('nothing to cut');
        }
        $rms = self::frameEnergy($pcm, $frameBytes);
        $frames = count($rms);
        if ($frames === 0) {
            throw SpeechNotCut::because('no audio');
        }
        $threshold = self::silenceThreshold($rms);
        [$first, $last] = self::speechBounds($rms, $threshold);
        if ($first === null || $last === null) {
            throw SpeechNotCut::because('no speech');
        }
        $edge = intdiv($this->keepEdgeMs, self::FRAME_MS);
        $ms = static fn (int $from, int $to): array => [$from * self::FRAME_MS, $to * self::FRAME_MS];
        if ($lines === 1) {
            return [$ms(max(0, $first - $edge), min($frames, $last + 1 + $edge * 2))];
        }

        $gaps = $this->gaps($rms, $threshold, $first, $last);
        if (count($gaps) < $lines - 1) {
            throw SpeechNotCut::because(sprintf('%d pauses for %d lines', count($gaps), $lines));
        }

        $weights = array_map(static fn (string $t): int => mb_strlen(trim($t)) + self::WEIGHT_PAD, $texts);
        $chosen = $this->place($gaps, $weights, $first, $last);

        $bounds = [];
        for ($i = 0; $i < $lines; $i++) {
            $start = $i === 0 ? $first : $gaps[$chosen[$i - 1]][1];
            $end = $i === $lines - 1 ? $last + 1 : $gaps[$chosen[$i]][0];
            $bounds[] = [$start, $end];
        }
        $this->assertShares($bounds, $weights);

        $out = [];
        foreach ($bounds as $i => [$start, $end]) {
            $left = $i === 0 ? max(0, $start - $edge) : max(self::middle($gaps[$chosen[$i - 1]]), $start - $edge);
            $right = $i === $lines - 1 ? min($frames, $end + $edge * 2) : min(self::middle($gaps[$chosen[$i]]), $end + $edge);
            $out[] = $ms($left, $right);
        }

        return $out;
    }

    /** @return list<float> RMS of every 10 ms frame */
    private static function frameEnergy(string $pcm, int $frameBytes): array
    {
        $frames = intdiv(strlen($pcm), $frameBytes);
        $out = [];
        for ($f = 0; $f < $frames; $f++) {
            /** @var array<int, int> $samples */
            $samples = unpack('v*', substr($pcm, $f * $frameBytes, $frameBytes));
            $sum = 0.0;
            foreach ($samples as $u) {
                $s = $u >= 32768 ? $u - 65536 : $u;
                $sum += $s * $s;
            }
            $out[] = sqrt($sum / max(1, count($samples)));
        }

        return $out;
    }

    /**
     * Speech is loud against its own loudest frames: 8 % of the 95th percentile, never under a floor
     * a synthesizer's near-digital silence stays below.
     *
     * @param  list<float>  $rms
     */
    private static function silenceThreshold(array $rms): float
    {
        $sorted = $rms;
        sort($sorted);
        $p95 = $sorted[(int) floor(0.95 * (count($sorted) - 1))];

        return max(60.0, $p95 * 0.08);
    }

    /**
     * @param  list<float>  $rms
     * @return array{0: int|null, 1: int|null}
     */
    private static function speechBounds(array $rms, float $threshold): array
    {
        $first = null;
        $last = null;
        foreach ($rms as $i => $value) {
            if ($value > $threshold) {
                $first ??= $i;
                $last = $i;
            }
        }

        return [$first, $last];
    }

    /**
     * Silent runs inside the speech, long enough to be a pause and not a stop consonant.
     *
     * @param  list<float>  $rms
     * @return list<array{0: int, 1: int}> [first silent frame, first frame after] in time order
     */
    private function gaps(array $rms, float $threshold, int $first, int $last): array
    {
        $min = max(1, intdiv($this->minGapMs, self::FRAME_MS));
        $gaps = [];
        $f = $first;
        while ($f <= $last) {
            if ($rms[$f] > $threshold) {
                $f++;

                continue;
            }
            $start = $f;
            while ($f <= $last && $rms[$f] <= $threshold) {
                $f++;
            }
            if ($f - $start >= $min) {
                $gaps[] = [$start, $f];
            }
        }

        return $gaps;
    }

    /**
     * The cheapest ordered choice of one pause per seam.
     *
     * @param  list<array{0: int, 1: int}>  $gaps
     * @param  list<int>  $weights
     * @return list<int> index into $gaps for every seam
     */
    private function place(array $gaps, array $weights, int $first, int $last): array
    {
        $seams = count($weights) - 1;
        $span = max(1, $last - $first + 1);
        $total = array_sum($weights);
        $expected = [];
        $running = 0;
        for ($i = 0; $i < $seams; $i++) {
            $running += $weights[$i];
            $expected[] = $first + $span * $running / $total;
        }

        $count = count($gaps);
        $cost = function (int $seam, int $gap) use ($gaps, $expected, $span): float {
            $offset = (self::middle($gaps[$gap]) - $expected[$seam]) / $span / $this->positionTolerance;
            $lengthMs = ($gaps[$gap][1] - $gaps[$gap][0]) * self::FRAME_MS;

            return $offset * $offset - min($lengthMs, 1200) / 600;
        };

        /** @var list<list<float>> $best */
        $best = [];
        /** @var list<list<int>> $from */
        $from = [];
        for ($seam = 0; $seam < $seams; $seam++) {
            $best[$seam] = array_fill(0, $count, INF);
            $from[$seam] = array_fill(0, $count, -1);
            $runningMin = INF;
            $runningArg = -1;
            for ($gap = $seam; $gap < $count - ($seams - 1 - $seam); $gap++) {
                if ($seam === 0) {
                    $best[0][$gap] = $cost(0, $gap);

                    continue;
                }
                // The previous seam's best over every earlier pause, kept as the scan moves right.
                if ($best[$seam - 1][$gap - 1] < $runningMin) {
                    $runningMin = $best[$seam - 1][$gap - 1];
                    $runningArg = $gap - 1;
                }
                if ($runningArg >= 0) {
                    $best[$seam][$gap] = $runningMin + $cost($seam, $gap);
                    $from[$seam][$gap] = $runningArg;
                }
            }
        }

        $end = -1;
        $endCost = INF;
        foreach ($best[$seams - 1] as $gap => $value) {
            if ($value < $endCost) {
                $endCost = $value;
                $end = $gap;
            }
        }
        if ($end < 0) {
            throw SpeechNotCut::because('no ordered placement of the seams');
        }

        $chosen = array_fill(0, $seams, 0);
        for ($seam = $seams - 1, $gap = $end; $seam >= 0; $seam--) {
            $chosen[$seam] = $gap;
            $gap = $from[$seam][$gap];
        }

        return array_values($chosen);
    }

    /**
     * @param  list<array{0: int, 1: int}>  $bounds
     * @param  list<int>  $weights
     */
    private function assertShares(array $bounds, array $weights): void
    {
        $audio = array_sum(array_map(static fn (array $b): int => $b[1] - $b[0], $bounds));
        $text = array_sum($weights);
        foreach ($bounds as $i => [$start, $end]) {
            $length = $end - $start;
            if ($length * self::FRAME_MS < 200) {
                throw SpeechNotCut::because("line {$i} is {$length}0 ms of sound");
            }
            $ratio = ($length / max(1, $audio)) / ($weights[$i] / $text);
            if ($ratio > $this->maxShareRatio || $ratio < 1 / $this->maxShareRatio) {
                throw SpeechNotCut::because(sprintf('line %d has %.2f× its share of the sound', $i, $ratio));
            }
        }
    }

    /** @param array{0: int, 1: int} $gap */
    private static function middle(array $gap): int
    {
        return intdiv($gap[0] + $gap[1], 2);
    }
}
