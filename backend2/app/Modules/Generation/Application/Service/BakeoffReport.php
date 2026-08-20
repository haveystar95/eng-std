<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Service;

use App\Modules\Generation\Application\Dto\BakeoffCallResult;
use App\Modules\Generation\Application\Dto\ProviderAvailability;
use App\Modules\Generation\Domain\ValueObject\BakeoffTrack;
use App\Modules\Generation\Domain\ValueObject\CandidateVerdict;
use App\Modules\Generation\Domain\ValueObject\CheckId;
use App\Modules\Generation\Domain\ValueObject\ProviderId;

/**
 * The file a person reads in the morning and decides from.
 *
 * Three tables, one per track, because the tracks can have different winners and a single ranking
 * would force the worse choice on one of them. Under each table, the examples — the numbers exist
 * to say WHICH rows are worth a human read, and a report of only counts asks the reader to trust an
 * automatic check about a question ("is this translation a good key") that no automatic check can
 * settle.
 *
 * Everything unmeasured is said out loud: a provider that was not run, a check that could not apply,
 * a cost that could not be priced. A blank cell is the one thing a decision document must never have.
 */
final readonly class BakeoffReport
{
    /** How many side-by-side examples per track — the наряд asks for 5–6. */
    private const EXAMPLES = 6;

    /**
     * @param  list<BakeoffCallResult>  $results
     * @param  list<ProviderAvailability>  $availability
     * @param  array<string, mixed>  $meta
     */
    public function render(array $results, array $availability, array $meta): string
    {
        $out = [];
        $out[] = '# Bake-off провайдеров генерации — ' . ($meta['label'] ?? 'run');
        $out[] = '';
        $out[] = (string) ($meta['header_line'] ?? '');
        $out[] = '';
        $out[] = 'Промпт **' . ($meta['prompt_version'] ?? '?') . '**, языки **'
            . ($meta['target_lang'] ?? '?') . ' ← ' . ($meta['source_lang'] ?? '?')
            . '**, run id `' . ($meta['run_id'] ?? '?') . '`.';
        $out[] = '';
        $out[] = '> Живой контент не изменён: прогон только читает термины и пишет в песочницу '
            . '(`bakeoff_runs` / `bakeoff_calls` / `bakeoff_candidates`).';
        $out[] = '';

        $out[] = $this->topicsSection($meta);
        $out[] = $this->trackSources($meta);
        $out[] = $this->providersSection($availability, $results);
        $out[] = $this->checksLegend();

        foreach (BakeoffTrack::cases() as $track) {
            $trackResults = array_values(array_filter($results, static fn (BakeoffCallResult $r): bool => $r->track === $track));
            if ($trackResults === []) {
                continue;
            }
            $out[] = $this->trackSection($track, $trackResults);

            // Only under track А: the store's own list is the baseline the generated one is judged
            // against, and repeating it under every track would be noise.
            if ($track === BakeoffTrack::Collections && is_array($meta['store_terms'] ?? null) && $meta['store_terms'] !== []) {
                /** @var list<array{text: string, translation: string}> $storeTerms */
                $storeTerms = $meta['store_terms'];
                $out[] = $this->storeSection((string) ($meta['store_topic'] ?? ''), $storeTerms);
            }
        }

        $out[] = $this->twoStageVsOneShot($results, $meta);
        $out[] = $this->howToRead();

        return implode("\n", array_filter($out, static fn (string $s): bool => $s !== "\0")) . "\n";
    }

    /**
     * What was asked, and why that. A comparison document has to state its own task: a reader who
     * cannot see the topic cannot tell a weak answer from a hard question.
     *
     * @param  array<string, mixed>  $meta
     */
    private function topicsSection(array $meta): string
    {
        $topics = $meta['topics'] ?? null;
        if (! is_array($topics) || $topics === []) {
            return "\0";
        }

        $lines = ['## Задание', '', '| Тема | Почему она |', '|---|---|'];
        foreach ($topics as $topic) {
            if (is_array($topic) && is_string($topic['key'] ?? null)) {
                $lines[] = '| ' . $this->cell($topic['key']) . ' | ' . $this->cell((string) ($topic['note'] ?? '—')) . ' |';
            }
        }
        $lines[] = '';
        $lines[] = 'Одно и то же задание, слово в слово, каждому провайдеру: промпт одной версии и '
            . 'одной формы, схема одна, проверки одни. Различаются только модели.';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * When a track's numbers come from a different run than its neighbours', say so at the top.
     *
     * A run can die half-way — credits run out, a limit bites — and the honest repair is to take
     * each track from the run that actually answered it. That is only honest while the reader can
     * see it, so this block is printed before any table and never omitted when it applies.
     *
     * @param  array<string, mixed>  $meta
     */
    private function trackSources(array $meta): string
    {
        $sources = $meta['track_sources'] ?? null;
        if (! is_array($sources) || $sources === []) {
            return "\0";
        }

        $lines = [
            '## Откуда цифры',
            '',
            'Отчёт собран из нескольких прогонов: каждая пара «трек + провайдер» взята из того '
            . 'прогона, где она ответила лучше всего — механическое правило «больше успешных '
            . 'вызовов», а не выбор понравившихся чисел. Сравнение остаётся честным: задание, '
            . 'промпт, схема и проверки у всех одни и те же, разбиение на прогоны — техническое.',
            '',
            '| Трек | Прогон | Успешных вызовов (все провайдеры) |',
            '|---|---|---|',
        ];
        foreach ($sources as $track => $source) {
            if (! is_array($source)) {
                continue;
            }
            $case = BakeoffTrack::tryFrom((string) $track);
            $lines[] = sprintf(
                '| %s | `%s` | %s из %s |',
                $case?->title() ?? (string) $track,
                (string) ($source['run'] ?? '?'),
                (string) ($source['ok'] ?? '?'),
                (string) ($source['total'] ?? '?'),
            );
        }
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Who ran, and — just as important — who did not and why.
     *
     * A key is only half of "available". A vendor can hold a valid key and refuse every call (no
     * credits, a spending cap), and that provider produced NOTHING while the plan counted it as a
     * participant. Left to the numbers alone it reads as a catastrophic quality result. So a
     * provider whose every call failed is named here with the error it actually returned.
     *
     * @param  list<ProviderAvailability>  $availability
     * @param  list<BakeoffCallResult>  $results
     */
    private function providersSection(array $availability, array $results): string
    {
        $lines = ['## Провайдеры', '', '| Провайдер | Модель | Участвовал | Почему нет |', '|---|---|---|---|'];
        foreach ($availability as $row) {
            $ran = array_values(array_filter(
                $results,
                static fn (BakeoffCallResult $r): bool => $r->provider === $row->provider,
            ));
            $answered = array_filter($ran, static fn (BakeoffCallResult $r): bool => $r->ok);

            [$participated, $reason] = match (true) {
                ! $row->available => ['**нет**', $row->reason],
                $ran === [] => ['**нет**', 'не было заданий'],
                // A key that opens no door: every single call came back an error.
                $answered === [] => ['**нет — все вызовы упали**', $this->firstError($ran)],
                default => ['да', count($answered) === count($ran) ? '—' : (count($ran) - count($answered)) . ' из ' . count($ran) . ' вызовов упали'],
            };

            $lines[] = sprintf('| %s | `%s` | %s | %s |', $row->provider->label(), $row->model, $participated, $reason);
        }
        $lines[] = '';

        return implode("\n", $lines);
    }

    /** @param list<BakeoffCallResult> $results */
    private function firstError(array $results): string
    {
        foreach ($results as $result) {
            if ($result->error !== null) {
                return $this->cell(mb_substr($result->error, 0, 240));
            }
        }

        return 'причина не записана';
    }

    private function checksLegend(): string
    {
        $lines = ['## Автопроверки', '', '| Код | Что проверяет |', '|---|---|'];
        foreach (CheckId::cases() as $check) {
            $lines[] = '| `' . $check->value . '` | ' . $check->label() . ' |';
        }
        $lines[] = '';
        $lines[] = 'Проверки ловят ИЗВЕСТНЫЕ классы брака, а не «качество». Набор, прошедший всё, '
            . 'по-прежнему может быть скучным или буквальным — для этого ниже примеры бок-о-бок.';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /** @param list<BakeoffCallResult> $results */
    private function trackSection(BakeoffTrack $track, array $results): string
    {
        $shape = $track->shape();
        $checks = CheckId::forShape($shape);

        $header = ['Провайдер', 'вызовов', 'ошибок вызова', 'items', 'чистых'];
        foreach ($checks as $check) {
            $header[] = $check->value;
        }
        $header[] = 'размер';
        $header[] = 'латентность med';
        $header[] = 'токены in/out';
        $header[] = '$';

        $lines = ['## ' . $track->title(), '', '| ' . implode(' | ', $header) . ' |',
            '|' . str_repeat('---|', count($header)), ];

        foreach ($this->byProvider($results) as $providerValue => $providerResults) {
            $lines[] = $this->providerRow(ProviderId::from($providerValue), $providerResults, $checks);
        }
        $lines[] = '';

        // When nobody trips a single check, the honest headline is that the checks did not separate
        // anyone — not that everyone is equally good. Left unsaid, a row of 100%s reads as a verdict.
        $lines[] = $this->verdictLine($results);

        if ($track === BakeoffTrack::OneShot) {
            $lines[] = $this->degradationTable($results);
        }

        $lines[] = $this->examples($track, $results);

        return implode("\n", $lines);
    }

    /**
     * What the store already holds for the topic that exists in it.
     *
     * One of the four topics is deliberately a collection that is already live, and the question a
     * reader actually has about it is not "which provider looks better" but "is any of this better
     * than what the learner is being shown today". Without this block that comparison is a memory
     * exercise.
     *
     * @param  list<array{text: string, translation: string}>  $terms
     */
    private function storeSection(string $topic, array $terms): string
    {
        if ($terms === []) {
            return '';
        }

        $lines = [
            '### Что в витрине сегодня по теме «' . $topic . '»',
            '',
            'Живой контент, прочитанный на момент прогона. Приведён для сравнения и НЕ изменялся.',
            '',
            '| # | Термин | Перевод |',
            '|---|---|---|',
        ];
        foreach ($terms as $i => $term) {
            $lines[] = sprintf('| %d | %s | %s |', $i + 1, $this->cell($term['text']), $this->cell($term['translation']));
        }
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * What the numbers in the table above did, and did not, settle.
     *
     * @param  list<BakeoffCallResult>  $results
     */
    private function verdictLine(array $results): string
    {
        $withItems = 0;
        $allClean = true;
        foreach ($this->byProvider($results) as $providerResults) {
            $items = 0;
            $clean = 0;
            foreach ($providerResults as $result) {
                $items += $result->batch?->total() ?? 0;
                $clean += $result->batch?->clean() ?? 0;
            }
            if ($items === 0) {
                continue;
            }
            $withItems++;
            if ($clean !== $items) {
                $allClean = false;
            }
        }

        if ($withItems < 2 || ! $allClean) {
            return "\0";
        }

        return implode("\n", [
            '> **Автопроверки на этом задании никого не различили: у всех 100% чистых.** Это не '
            . 'значит «все одинаково хороши» — значит, что известные классы брака (чужой язык, '
            . 'дубли, пустые поля, потерянный адресат) здесь не сработали ни у кого, и выбор '
            . 'решается ЧТЕНИЕМ полных списков ниже плюс ценой и латентностью.',
            '',
        ]);
    }

    /**
     * @param  list<BakeoffCallResult>  $results
     * @param  list<CheckId>  $checks
     */
    private function providerRow(ProviderId $provider, array $results, array $checks): string
    {
        $calls = count($results);
        $failedCalls = count(array_filter($results, static fn (BakeoffCallResult $r): bool => ! $r->ok));

        $items = 0;
        $clean = 0;
        $perCheck = [];
        $sizeMisses = 0;
        $latencies = [];
        $tokensIn = 0;
        $tokensOut = 0;
        $cost = 0.0;
        $priced = true;

        foreach ($results as $result) {
            if ($result->latencyMs !== null) {
                $latencies[] = $result->latencyMs;
            }
            $tokensIn += $result->tokensIn ?? 0;
            $tokensOut += $result->tokensOut ?? 0;
            if ($result->costUsd === null && $result->ok) {
                $priced = false;
            }
            $cost += (float) ($result->costUsd ?? 0);

            if ($result->batch === null) {
                continue;
            }
            $items += $result->batch->total();
            $clean += $result->batch->clean();
            foreach ($checks as $check) {
                $perCheck[$check->value] = ($perCheck[$check->value] ?? 0) + $result->batch->failures($check);
            }
            if (in_array(CheckId::Size, $result->batch->batchFailures, true)) {
                $sizeMisses++;
            }
        }

        $cells = [
            $provider->label(),
            (string) $calls,
            $failedCalls > 0 ? '**' . $failedCalls . '**' : '0',
            (string) $items,
            $items > 0 ? sprintf('**%d** (%d%%)', $clean, (int) round(100 * $clean / $items)) : '—',
        ];
        foreach ($checks as $check) {
            $n = $perCheck[$check->value] ?? 0;
            $cells[] = $n === 0 ? '0' : (string) $n;
        }
        $cells[] = $sizeMisses === 0 ? 'ок' : $sizeMisses . ' из ' . $calls;
        $cells[] = $latencies === [] ? '—' : $this->median($latencies) . ' мс';
        $cells[] = $tokensIn . '/' . $tokensOut;
        // An unpriced call must not silently become $0 — the cheapest column would go to whichever
        // vendor nobody entered a rate for.
        $cells[] = $priced ? '$' . number_format($cost, 4, '.', '') : '$' . number_format($cost, 4, '.', '') . ' (неполно)';

        return '| ' . implode(' | ', $cells) . ' |';
    }

    /**
     * The tail hypothesis, in numbers: does the second half of a long answer carry more defects than
     * the first? Reported per provider, averaged over that provider's calls.
     *
     * @param  list<BakeoffCallResult>  $results
     */
    private function degradationTable(array $results): string
    {
        $lines = [
            '### Деградация по позиции в списке',
            '',
            'Доля items с браком в первой половине ответа против второй. Гипотеза: «хвост длинного '
            . 'ответа халтурит». Разница в пределах пары процентов на выборке этого размера — шум.',
            '',
            '| Провайдер | 1-я половина | 2-я половина | Δ |',
            '|---|---|---|---|',
        ];

        foreach ($this->byProvider($results) as $providerValue => $providerResults) {
            $firstBad = 0;
            $firstN = 0;
            $secondBad = 0;
            $secondN = 0;
            foreach ($providerResults as $result) {
                if ($result->batch === null) {
                    continue;
                }
                [$r1, $r2, $n1, $n2] = $result->batch->halves();
                $firstBad += (int) round($r1 * $n1);
                $firstN += $n1;
                $secondBad += (int) round($r2 * $n2);
                $secondN += $n2;
            }
            if ($firstN === 0 || $secondN === 0) {
                continue;
            }
            $p1 = 100 * $firstBad / $firstN;
            $p2 = 100 * $secondBad / $secondN;
            $lines[] = sprintf(
                '| %s | %d%% (%d/%d) | %d%% (%d/%d) | %+d п.п. |',
                ProviderId::from($providerValue)->label(),
                (int) round($p1), $firstBad, $firstN,
                (int) round($p2), $secondBad, $secondN,
                (int) round($p2 - $p1),
            );
        }
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Side-by-side rows: the same task, every provider's answer to it, defects named.
     *
     * Chosen by disagreement — the tasks where the providers' defect counts differ most — because a
     * row everyone got right and a row everyone got wrong both say nothing about which to pick.
     *
     * @param  list<BakeoffCallResult>  $results
     */
    private function examples(BakeoffTrack $track, array $results): string
    {
        $lines = ['### Примеры бок-о-бок', ''];

        // task key → provider → the items it produced
        $byTask = [];
        foreach ($results as $result) {
            if ($result->batch === null) {
                continue;
            }
            foreach ($result->batch->verdicts as $verdict) {
                $byTask[$result->taskKey][$result->provider->value][] = $verdict;
            }
        }

        if ($byTask === []) {
            return implode("\n", [...$lines, '_Нечего показать: ни один провайдер не ответил._', '']);
        }

        if ($track === BakeoffTrack::Enrichment) {
            // One term per block: the whole point of this track is the same term rendered by each
            // provider, read next to the others.
            $shown = 0;
            foreach ($byTask as $taskKey => $providers) {
                if ($shown >= self::EXAMPLES) {
                    break;
                }
                if (! $this->interesting($providers)) {
                    continue;
                }
                $shown++;
                $lines[] = '**' . $shown . '. `' . $taskKey . '`**';
                $lines[] = '';
                $lines[] = '| Провайдер | Перевод | Пример | Опции | Брак |';
                $lines[] = '|---|---|---|---|---|';
                foreach ($providers as $providerValue => $verdicts) {
                    foreach ($verdicts as $verdict) {
                        $lines[] = $this->enrichRow(ProviderId::from($providerValue), $verdict);
                    }
                }
                $lines[] = '';
            }
            if ($shown === 0) {
                $lines[] = '_Провайдеры не разошлись ни на одном термине._';
                $lines[] = '';
            }

            return implode("\n", $lines);
        }

        // A and C: a fragment of each provider's list for the SAME topic, so the lists can be read
        // against each other (and, for the existing topic, against what is in the store today).
        $shown = 0;
        foreach ($byTask as $taskKey => $providers) {
            if ($shown >= self::EXAMPLES) {
                break;
            }
            $shown++;
            $lines[] = '**' . $shown . '. Тема: ' . $taskKey . '**';
            $lines[] = '';
            foreach ($providers as $providerValue => $verdicts) {
                // The WHOLE list, not a head. This block is the one a person reads to choose a
                // provider, and a collection cannot be judged from its first six items — the tail
                // is exactly where a set runs out of ideas and starts repeating the topic back.
                $lines[] = '_' . ProviderId::from($providerValue)->label() . '_ — все ' . count($verdicts) . ':';
                $lines[] = '';
                $lines[] = '| # | Термин | Перевод | Пример | Брак |';
                $lines[] = '|---|---|---|---|---|';
                foreach ($verdicts as $verdict) {
                    $lines[] = sprintf(
                        '| %d | %s | %s | %s | %s |',
                        $verdict->item->position + 1,
                        $this->cell($verdict->item->text),
                        $this->cell($verdict->item->translation ?? '—'),
                        $this->cell($verdict->item->example ?? '**нет**'),
                        $verdict->isClean() ? '—' : $this->cell($verdict->reason()),
                    );
                }
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }

    private function enrichRow(ProviderId $provider, CandidateVerdict $verdict): string
    {
        $item = $verdict->item;

        return sprintf(
            '| %s | %s | %s | %s | %s |',
            $provider->label(),
            $this->cell($item->translation ?? '—'),
            $this->cell($item->example ?? '**нет**'),
            $item->options === [] ? '—' : $this->cell(implode(' / ', $item->options)),
            $verdict->isClean() ? '—' : $this->cell($verdict->reason()),
        );
    }

    /**
     * Is this task worth a reader's time — did the providers actually disagree about it?
     *
     * @param  array<string, list<CandidateVerdict>>  $providers
     */
    private function interesting(array $providers): bool
    {
        if (count($providers) < 2) {
            return true; // one provider ran: everything it produced is all there is to show
        }

        $defects = [];
        foreach ($providers as $verdicts) {
            $defects[] = count(array_filter($verdicts, static fn (CandidateVerdict $v): bool => ! $v->isClean()));
        }

        return count(array_unique($defects)) > 1;
    }

    /**
     * The head-to-head the experiment exists for: one topic, built two ways.
     *
     * @param  list<BakeoffCallResult>  $results
     * @param  array<string, mixed>  $meta
     */
    private function twoStageVsOneShot(array $results, array $meta): string
    {
        // The section compares two PIPELINE SHAPES, so it needs both of them. On a run that covered
        // only one track it would render as a heading over a single row and read like a comparison
        // that had been made — which is the opposite of what happened.
        $tracks = [];
        foreach ($results as $result) {
            if ($result->ok) {
                $tracks[$result->track->value] = true;
            }
        }
        if (! isset($tracks[BakeoffTrack::OneShot->value]) || ! isset($tracks[BakeoffTrack::Enrichment->value])) {
            return "\0";
        }

        $lines = [
            '## Два этапа (А + Б) против one-shot (В)',
            '',
            'Стоимость готовой коллекции целиком. Двухэтапная схема — это вызов трека А плюс по '
            . 'одному вызову обогащения на каждый термин; one-shot — один вызов. Цифры ниже — '
            . 'фактическая стоимость прогона, пересчитанная на коллекцию из '
            . ($meta['collection_size'] ?? '?') . ' терминов.',
            '',
            '| Провайдер | Схема | $ на коллекцию | Латентность | Чистых items |',
            '|---|---|---|---|---|',
        ];

        $size = is_int($meta['collection_size'] ?? null) ? $meta['collection_size'] : 12;

        // Only providers that actually produced something. A row of $0.0000 for a vendor whose every
        // call was refused reads as "free", which is the opposite of what happened.
        $providers = [];
        foreach ($results as $result) {
            if ($result->ok) {
                $providers[$result->provider->value] = true;
            }
        }

        foreach (array_keys($providers) as $providerValue) {
            $twoStage = 0.0;
            $complete = true;

            foreach ([BakeoffTrack::Collections, BakeoffTrack::Enrichment, BakeoffTrack::OneShot] as $track) {
                $ofTrack = array_values(array_filter(
                    $results,
                    static fn (BakeoffCallResult $r): bool => $r->track === $track
                        && $r->provider->value === $providerValue && $r->ok,
                ));
                if ($ofTrack === []) {
                    if ($track !== BakeoffTrack::OneShot) {
                        $complete = false;
                    }

                    continue;
                }

                $cost = 0.0;
                $latency = [];
                $items = 0;
                $clean = 0;
                foreach ($ofTrack as $r) {
                    $cost += (float) ($r->costUsd ?? 0);
                    if ($r->latencyMs !== null) {
                        $latency[] = $r->latencyMs;
                    }
                    $items += $r->batch?->total() ?? 0;
                    $clean += $r->batch?->clean() ?? 0;
                }

                // Per-collection: A and C are already one call per collection; B is one call per
                // TERM, so its per-collection cost is the mean term cost times the collection size.
                $perCollection = $track === BakeoffTrack::Enrichment
                    ? ($cost / count($ofTrack)) * $size
                    : $cost / count($ofTrack);

                if ($track !== BakeoffTrack::OneShot) {
                    $twoStage += $perCollection;
                }

                $lines[] = sprintf(
                    '| %s | %s | $%s | %s | %s |',
                    ProviderId::from($providerValue)->label(),
                    match ($track) {
                        BakeoffTrack::Collections => 'А (список)',
                        BakeoffTrack::Enrichment => 'Б (обогащение, ×' . $size . ')',
                        BakeoffTrack::OneShot => '**В (one-shot, один вызов)**',
                    },
                    number_format($perCollection, 4, '.', ''),
                    $latency === []
                        ? '—'
                        : ($track === BakeoffTrack::Enrichment
                            ? $this->median($latency) . ' мс × ' . $size . ' терминов'
                            : $this->median($latency) . ' мс'),
                    $items > 0 ? (int) round(100 * $clean / $items) . '%' : '—',
                );
            }

            // The line the whole table exists for: what the current two-step pipeline costs, added
            // up, so the one-shot row above it is compared against a number and not against
            // arithmetic the reader has to do.
            if ($complete) {
                $lines[] = sprintf(
                    '| %s | **А + Б — текущая схема, итого** | **$%s** | — | — |',
                    ProviderId::from($providerValue)->label(),
                    number_format($twoStage, 4, '.', ''),
                );
            }
        }

        $lines[] = '';
        $lines[] = 'Строка «А + Б» — это то, во что обходится готовая коллекция СЕЙЧАС; строка В — '
            . 'альтернатива ей одним вызовом. Трек В — **эксперимент**: он не заменяет А и Б, '
            . 'решение по схеме пайплайна принимает архитектор.';
        $lines[] = '';

        return implode("\n", $lines);
    }

    private function howToRead(): string
    {
        return implode("\n", [
            '## Как это читать',
            '',
            '- Столбец «чистых» — items, не сработавшие НИ ПО ОДНОЙ проверке. Это самый строгий '
            . 'показатель и самый честный из автоматических.',
            '- Столбцы проверок — сколько items сработало по каждой. Один item может сработать по '
            . 'нескольким, поэтому сумма столбцов больше числа грязных items.',
            '- Победитель может быть РАЗНЫМ в треках А и Б: выбирать список слов и писать ключ к '
            . 'выданному термину — разные задачи, и пайплайны независимы.',
            '- Провайдер, помеченный «нет» в таблице провайдеров, не участвовал вообще; его '
            . 'отсутствие — не результат.',
            '',
            '### Чего цифры НЕ знают',
            '',
            '- **`isomorphism` — грубый детектор, и он завышает.** Он ищет отдельное слово-'
            . 'соответствие и не видит лица, выраженного формой глагола: «Can you tell me…» → '
            . '«Можете рассказать мне…» помечено как «потеряно: you», хотя «Можете» — это и есть '
            . 'второе лицо. Такой хит — КАНДИДАТ на человеческий взгляд, а не приговор; правило '
            . 'таким и задумано (см. `AddresseeIsomorphism`). Сравнивать провайдеров по этому '
            . 'столбцу можно — они меряются одной линейкой; читать его как «столько-то сломанных '
            . 'карточек» нельзя.',
            '- **`lang_source` видит только половину класса.** Украинские буквы (і/ї/є/ґ) и чужой '
            . 'скрипт — да; украинское слово, написанное общими буквами («закрити рахунок»), — нет. '
            . 'Ноль в этом столбце не означает «украинского нет», означает «буквенного нет».',
            '- **Совпадение опции по смыслу не проверяется.** Дубль по строке ловится, синоним '
            . 'верного ответа — нет: это решается чтением, а эвристика дала бы цифру, которую '
            . 'нельзя проверить.',
            '- Ничего из перечисленного не решается добавлением проверки «на глазок»: цифры честны '
            . 'ровно настолько, насколько названы их границы.',
            '',
        ]);
    }

    /**
     * @param  list<BakeoffCallResult>  $results
     * @return array<string, list<BakeoffCallResult>>
     */
    private function byProvider(array $results): array
    {
        $out = [];
        foreach ($results as $result) {
            $out[$result->provider->value][] = $result;
        }

        return $out;
    }

    /** @param list<int> $values */
    private function median(array $values): int
    {
        sort($values);
        $n = count($values);

        return $n === 0 ? 0 : (int) round($values[intdiv($n, 2)]);
    }

    /** Markdown table cells break on pipes and newlines; both are content here. */
    private function cell(string $value): string
    {
        return str_replace(['|', "\n"], ['\\|', ' '], trim($value));
    }
}
