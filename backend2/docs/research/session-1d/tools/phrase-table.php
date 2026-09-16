<?php

declare(strict_types=1);

/**
 * SESSION-1d · THE PHRASE STAGE, READ BACK — what the report prints about a dealt «Фразы»: per frame its fillers, its
 * cards with the kind and the filler each is said with, the positions and the smallest distance between two of its
 * cards (cards of OTHER frames in between), and the check «one filler — once» over its recognitions. Shared by
 * `probe.php` (the clean fake lesson, no database) and `live-day.php` (e2e).
 */

use App\Modules\Plan\Domain\Assembly\PhraseSeries;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Service\DayPace;
use App\Modules\Plan\Domain\ValueObject\CardKind;

/**
 * @param  list<DayCard>  $cards  the stage «Фразы» in its order
 * @return array{rows: list<array<string, mixed>>, violations: list<string>, repeats: list<string>}
 */
function s1dPhraseTable(array $cards): array
{
    $recognitions = [CardKind::PhraseSlot, CardKind::PhraseChooseBack, CardKind::PhraseSlotListen, CardKind::PhraseAssemble];
    $byFrame = [];
    foreach ($cards as $pos => $card) {
        $key = ($card->payload()['scene_id'] ?? '').':'.$card->unitRef();
        $byFrame[$key][] = ['pos' => $pos + 1, 'card' => $card];
    }
    $rows = [];
    $violations = [];
    $repeats = [];
    foreach ($byFrame as $key => $entries) {
        $ref = substr($key, strrpos($key, ':') + 1);
        $fillers = '—';
        foreach ($entries as $e) {
            if ($e['card']->kind() === CardKind::PhraseIntro) {
                $slot = $e['card']->payload()['frame']['slot'] ?? null;
                $fillers = $slot === null ? 'без окна' : implode(', ', array_map(static fn (array $f): string => $f['index'].': '.$f['target'].($f['in_dialogue'] ? ' (said)' : ''), $slot['fillers']));
            }
        }
        $gaps = [];
        $previous = null;
        foreach ($entries as $e) {
            if ($previous !== null) {
                $gaps[] = $e['pos'] - $previous - 1;
            }
            $previous = $e['pos'];
        }
        $min = $gaps === [] ? null : min($gaps);
        if ($min !== null && $min < 2) {
            $violations[] = "{$ref}: min distance {$min}";
        }
        $seen = [];
        foreach ($entries as $e) {
            if (! in_array($e['card']->kind(), $recognitions, true)) {
                continue;
            }
            $f = PhraseSeries::fillerOf($e['card']->kind(), $e['card']->payload());
            $mark = $f === null ? 'frame' : (string) $f;
            if (isset($seen[$mark])) {
                $repeats[] = "{$ref}: filler {$mark} twice";
            }
            $seen[$mark] = true;
        }
        $rows[] = [
            'frame' => $ref,
            'fillers' => $fillers,
            'cards' => count($entries),
            'kinds' => implode(' → ', array_map(static function (array $e): string {
                $f = PhraseSeries::fillerOf($e['card']->kind(), $e['card']->payload());

                return '#'.$e['pos'].' '.$e['card']->kind()->value.($f === null ? '' : '·f'.$f);
            }, $entries)),
            'gaps' => implode(', ', $gaps),
            'min' => $min,
        ];
    }

    return ['rows' => $rows, 'violations' => array_values(array_unique($violations)), 'repeats' => $repeats];
}

/** @param list<DayCard> $cards */
function s1dPrintPhrases(array $cards, DayPace $pace): void
{
    $table = s1dPhraseTable($cards);
    echo "\n| каркас | наполнения | карточек | карточки по порядку (позиция · вид · наполнение) | расстояния (чужих между) | мин |\n|---|---|---|---|---|---|\n";
    foreach ($table['rows'] as $row) {
        echo "| {$row['frame']} | {$row['fillers']} | {$row['cards']} | {$row['kinds']} | {$row['gaps']} | ".($row['min'] ?? '—')." |\n";
    }
    echo "\nэтап «Фразы»: ".count($cards).' карточек, '.$pace->secondsOf($cards).' с по DayPace';
    echo "\nправило «минимум две чужие»: ".($table['violations'] === [] ? 'держится везде' : 'нарушено — '.implode('; ', $table['violations']));
    echo "\nправило «одно наполнение — одно узнавание»: ".($table['repeats'] === [] ? 'держится' : 'нарушено — '.implode('; ', $table['repeats']))."\n";
}
