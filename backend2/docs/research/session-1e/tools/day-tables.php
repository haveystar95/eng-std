<?php

declare(strict_types=1);

/**
 * SESSION-1e · THE DAY, READ BACK FOR THE REPORT — the words' checks (word → kind → direction), the options of every
 * `word_listen`, the `phrase_combine` (its exchange, whether the partner's line asks, `said` of every frame), and which
 * fillers of which frames no card shows. Shared by `probe.php` (the clean fake lesson, no database), `outline.php` and
 * `live-day.php` (e2e); the phrase table is SESSION-1d's own (`../../session-1d/tools/phrase-table.php`).
 */

use App\Modules\Plan\Domain\Assembly\SceneMaterial;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Service\DayPace;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\Stage;

require_once __DIR__.'/../../session-1d/tools/phrase-table.php';

/**
 * A dealt card as a plain shape — a DayCard of a day, or a draft of a stage.
 *
 * @return array{kind: CardKind, ref: string, payload: array<string, mixed>, stage: Stage}
 */
function s1eCard(DayCard $card): array
{
    return ['kind' => $card->kind(), 'ref' => $card->unitRef(), 'payload' => $card->payload(), 'stage' => $card->stage()];
}

/** @param list<DayCard> $cards */
function s1eStageTable(array $cards, DayPace $pace): void
{
    echo "| этап | карточек | секунд по DayPace | минут |\n|---|---|---|---|\n";
    $total = 0;
    foreach (Stage::ordered() as $stage) {
        $of = array_values(array_filter($cards, static fn (DayCard $c): bool => $c->stage() === $stage));
        $seconds = $pace->secondsOf($of);
        $total += $seconds;
        echo "| {$stage->value} | ".count($of)." | {$seconds} | ".DayPace::minutes($seconds)." |\n";
    }
    echo '| **день** | **'.count($cards)."** | {$total} | **".DayPace::minutes($total)."** |\n";
}

/**
 * Word → kind → direction, in the words' order, and the count of every check kind.
 *
 * @param  list<DayCard>  $cards
 * @return array<string, int> kind → how many
 */
function s1ePrintWords(array $cards, SceneMaterial $scene): array
{
    $checks = [];
    foreach ($cards as $card) {
        if ($card->stage() === Stage::Words && ! in_array($card->kind(), [CardKind::WordIntro, CardKind::WordRepeat], true)) {
            $checks[$card->unitRef()] = $card;
        }
    }
    $counts = ['word_choose' => 0, 'word_listen' => 0, 'word_in_line' => 0, 'word_assemble' => 0];
    echo "\n| слово | многословное | строка дня | проверка | направление |\n|---|---|---|---|---|\n";
    foreach ($scene->vocabulary() as $i => $term) {
        $check = $checks[$term->ref()] ?? null;
        $multi = count(\App\Modules\Plan\Domain\Assembly\WordCards::words($term->textTarget(), $scene->target)) >= 2;
        $line = \App\Modules\Plan\Domain\Service\WordUsage::of($scene->lesson, $term->ref(), $term->textTarget()) !== null;
        if ($check !== null) {
            $counts[$check->kind()->value]++;
        }
        echo "| {$term->ref()} «{$term->textTarget()}» | ".($multi ? 'да' : 'нет').' | '.($line ? 'да' : 'нет').' | '
            .($check?->kind()->value ?? '—').' | '.($check?->payload()['direction'] ?? '—')." |\n";
    }
    echo 'проверок по видам: '.json_encode($counts)."\n";

    return $counts;
}

/** @param list<DayCard> $cards */
function s1ePrintListen(array $cards): void
{
    foreach ($cards as $card) {
        if ($card->kind() !== CardKind::WordListen) {
            continue;
        }
        $p = $card->payload();
        $texts = array_map(static fn (array $o): string => ($o['id'] === $p['correct'] ? '**'.$o['text'].'**' : $o['text']), $p['options']);
        echo "- word_listen {$card->unitRef()}: direction={$p['direction']}, звук {$p['audio']['ref']}; варианты: ".implode(' · ', $texts)
            .'; ключи: '.implode(', ', array_keys($p))."\n";
    }
}

/** @param list<DayCard> $cards */
function s1ePrintCombine(array $cards, SceneMaterial $scene): void
{
    foreach ($cards as $card) {
        if ($card->kind() !== CardKind::PhraseCombine) {
            continue;
        }
        $p = $card->payload();
        $exchange = $scene->exchange((int) $p['exchange']['step']);
        $partner = $exchange?->partner();
        echo "- phrase_combine: обмен {$p['exchange']['ref']} ({$p['exchange']['kind']}), реплика A «".($partner?->textTarget ?? '—').'» — вопрос: '
            .json_encode($partner !== null && $scene->asks($partner->textTarget)).", верный каркас {$p['correct_frame']}, correct_filler=".json_encode($p['correct_filler'])
            .', источник: '.$card->source()->value."\n";
        foreach ($p['frames'] as $frame) {
            $said = $frame['said'] ?? null;
            echo "  - {$frame['ref']} «{$frame['frame_target']}» → said ".($said === null ? '—' : "#{$said['index']} «{$said['text_target']}» / «{$said['text_native']}», звук {$said['audio']['ref']}")."\n";
        }
    }
}

/**
 * Which fillers of which frames the day's cards show — every filler list of every card read (frames, chips, options).
 *
 * @param  list<DayCard>  $cards
 * @param  list<string>  $unreadable  the addresses of the scene's `filler.native_seam` findings
 */
function s1ePrintHidden(array $cards, SceneMaterial $scene, array $unreadable): void
{
    $shown = [];
    $walk = static function (mixed $value) use (&$walk, &$shown): void {
        if (! is_array($value)) {
            return;
        }
        if (isset($value['ref'], $value['slot']['fillers']) && is_array($value['slot']['fillers'])) {
            foreach ($value['slot']['fillers'] as $filler) {
                $shown[$value['ref']][$filler['index']] = true;
            }
        }
        foreach ($value as $item) {
            $walk($item);
        }
    };
    foreach ($cards as $card) {
        $walk($card->payload());
    }
    echo "\n| каркас | наполнение | находка судьи | in_dialogue / said | скрыто сборкой | в карточках дня (frame.slot.fillers) |\n|---|---|---|---|---|---|\n";
    foreach ($scene->phrases() as $phrase) {
        foreach ($phrase->frame()?->fillers() ?? [] as $index => $filler) {
            $address = $phrase->ref().'.f'.($index + 1);
            $flag = in_array($address, $unreadable, true);
            if (! $flag) {
                continue;
            }
            echo "| {$phrase->ref()} | {$index}: {$filler->target} / {$filler->native} | {$address} | "
                .json_encode($filler->inDialogue).' / '.json_encode($scene->saidIndex($phrase) === $index).' | '
                .json_encode($scene->hides($phrase->ref(), $index)).' | '.json_encode(isset($shown[$phrase->ref()][$index]))." |\n";
        }
    }
}
