<?php

declare(strict_types=1);

namespace App\Modules\Generation\Presentation\Console;

use App\Modules\Generation\Application\Dto\TranslationKeyAuditRow;
use App\Modules\Generation\Application\Dto\TranslationKeyAuditView;
use App\Modules\Generation\Application\Query\GetTranslationKeyAudit;
use App\Modules\Generation\Application\Query\GetTranslationKeyAuditHandler;
use App\Modules\Shared\Infrastructure\Support\ExportHeader;
use Illuminate\Console\Command;

/**
 * Finds translations that have stopped pointing at their own term, and hands them to a human
 * (QA-17).
 *
 * The defect: a translation is the KEY, not prose. «Tell us about a challenge you faced» came back
 * as «Расскажите о вызове, с которым вы столкнулись» — fluent, and unanswerable, because `Tell me…`,
 * `Tell us…` and `Describe…` all fit it equally well. The learner answers honestly and the log
 * records a lapse. v8 of the generation prompt is where we ask for this not to happen again; this
 * command is what finds the content that already shipped.
 *
 * By default it sweeps the WHOLE showcase — every learner language the store holds, not the one
 * language someone remembered to pass. The per-language runs it replaced left the answer split
 * across two files with two different snapshots, and the language nobody re-ran is exactly where an
 * unreadable key survives. `--source-lang=` still narrows it to one when that is what you want.
 *
 * It writes an export and NOTHING ELSE — no `--apply`, deliberately. The detector is coarse by
 * design (Vocabulary's `AddresseeIsomorphism`): the learner languages carry a person in ways it
 * cannot see, so a hit is a candidate for the owner to read, never a verdict. A heuristic this rough
 * with a write path would quietly rewrite the very content it was asked to audit; the accepted
 * corrections go back through the existing apply mechanism, as a separate, deliberate pass.
 *
 * It lives in Generation with the other content audits because the report needs facts from TWO
 * modules — Vocabulary judges the key, Collections says which decks ask it — and Generation's
 * Application is the layer allowed to hold both. Vocabulary reaching into `collection_items` to
 * fetch a deck title would have been the shorter route and a boundary violation deptrac cannot see,
 * because the coupling would have been raw table names.
 */
final class AuditTranslationKeysCommand extends Command
{
    protected $signature = 'vocab:audit-translation-keys
        {--term-lang=en : the term side language}
        {--source-lang= : one learner language; empty sweeps every language the store has}
        {--out= : write the export here (default storage/app/translation-keys-audit.md)}';

    protected $description = 'Аудит переводов-ключей: термин адресует кого-то, а перевод — нет. Только выгрузка.';

    public function handle(GetTranslationKeyAuditHandler $audit): int
    {
        $termLang = $this->stringOption('term-lang') ?? 'en';
        $sourceLang = $this->stringOption('source-lang');
        $path = $this->stringOption('out') ?? storage_path('app/translation-keys-audit.md');

        $view = $audit(new GetTranslationKeyAudit($termLang, $sourceLang));

        $this->writeExport($path, $view, $termLang);

        $this->line('просмотрено пар: ' . $view->seen
            . ' (терминных ' . array_sum($view->seenTermsByLang)
            . ', примерных ' . array_sum($view->seenExamplesByLang) . ')');
        $this->line('кандидатов на вычитку: ' . count($view->rows)
            . ' (LOST ' . $this->countForDirection($view->rows, 'lost')
            . ', EXTRA ' . $this->countForDirection($view->rows, 'extra') . ')');

        $this->table(
            ['язык', 'терминных пар', 'примерных пар', 'кандидатов', 'правило знает язык'],
            array_map(
                fn (string $lang, int $pairs): array => [
                    $lang,
                    (string) $pairs,
                    (string) ($view->seenExamplesByLang[$lang] ?? 0),
                    (string) $this->countForLang($view->rows, $lang),
                    in_array($lang, $view->ruleLanguages, true) ? 'да' : 'НЕТ — правило молчит',
                ],
                array_keys($view->seenTermsByLang),
                $view->seenTermsByLang,
            ),
        );

        $byGroup = $this->byGroup($view->rows, $view->groupNames);
        $this->table(
            ['группа', 'кандидатов'],
            array_map(
                static fn (string $g, int $n): array => [$g, (string) $n],
                array_keys($byGroup),
                array_values($byGroup),
            ),
        );

        $byCollection = $this->byCollection($view->rows);
        $this->table(
            ['коллекция', 'кандидатов'],
            array_map(
                static fn (string $c, int $n): array => [$c, (string) $n],
                array_keys($byCollection),
                array_values($byCollection),
            ),
        );

        $this->info("выгрузка: {$path}");

        return self::SUCCESS;
    }

    private function writeExport(string $path, TranslationKeyAuditView $view, string $termLang): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($path, $this->markdown($view, $termLang));
    }

    private function markdown(TranslationKeyAuditView $view, string $termLang): string
    {
        $header = ExportHeader::now();
        $langs = array_keys($view->seenTermsByLang);
        $direction = $langs === []
            ? "направление: `{$termLang}` → (в витрине нет переводов)"
            : "направление: `{$termLang}` → `" . implode('`, `', $langs) . '`';

        $lines = [
            $header->comment(),
            '# Переводы-ключи на вычитку',
            '',
            $header->line($direction),
            '',
            'Ключ должен ОДНОЗНАЧНО указывать на свой источник. Ломается это в обе стороны, и обе',
            'здесь:',
            '',
            '- **LOST** — источник к кому-то обращается, а перевод этого не несёт: по такому переводу',
            '  нельзя однозначно восстановить источник, и честный ответ уходит в лог как промах.',
            '- **EXTRA** — перевод несёт то, чего в источнике нет: «I get along with my team» →',
            '  «Я **хорошо** лажу со своей командой». Тут наоборот — учащийся отвечает верно, а ключ',
            '  требует слова, которого в источнике никогда не было.',
            '',
            'Прогон по ВСЕЙ витрине: языки не задавались списком, а взяты из самого контента —',
            'сколько языков перевода в базе, столько и просмотрено.',
            '',
            '**Детектор грубый и ничего не правит.** Язык перевода выражает лицо способами, которых он',
            'не видит: обращение может быть уже зашито в глагол, родительный падеж — подразумеваться,',
            'притяжательное «свой»/«свій» — стоять за «ваш»/«ваш». Строка здесь — кандидат на прочтение,',
            'а не приговор. Правки едут отдельным заходом через существующий apply-механизм.',
            '',
            "Просмотрено пар: **{$view->seen}** — терминных **" . array_sum($view->seenTermsByLang)
                . '**, примерных **' . array_sum($view->seenExamplesByLang) . '**. Кандидатов: **'
                . count($view->rows) . '** — LOST **' . $this->countForDirection($view->rows, 'lost')
                . '**, EXTRA **' . $this->countForDirection($view->rows, 'extra') . '**.',
            '',
            'Пример — такой же ключ, как термин: его показывают, произносят и отвечают на него, поэтому',
            'потерянный адресат в переводе примера ломает карточку ровно так же.',
            '',
            '## Языки',
            '',
            'Колонка «правило знает язык» — не формальность: для языка без списка соответствий детектор',
            'молчит по построению, и ноль кандидатов там означает «не проверено», а не «чисто».',
            '',
            '| язык | терминных пар | примерных пар | кандидатов | правило знает язык |',
            '|---|---|---|---|---|',
        ];

        foreach ($view->seenTermsByLang as $lang => $pairs) {
            $lines[] = sprintf(
                '| `%s` | %d | %d | %d | %s |',
                $lang,
                $pairs,
                $view->seenExamplesByLang[$lang] ?? 0,
                $this->countForLang($view->rows, $lang),
                in_array($lang, $view->ruleLanguages, true) ? 'да' : '**НЕТ — детектор здесь молчит**',
            );
        }
        $lines[] = '';

        if ($view->rows === []) {
            $lines[] = '_Нечего вычитывать._';
            $lines[] = '';

            return implode("\n", $lines) . "\n";
        }

        $lines[] = '## Кандидаты';
        $lines[] = '';
        $lines[] = 'Колонка «что не так» читается по направлению. LOST: слово САМОГО источника, которое';
        $lines[] = 'перевод не отразил, и формы, которые детектор счёл бы ответом на него. EXTRA: слово';
        $lines[] = 'ПЕРЕВОДА, которого источник не давал, и слова источника, которые его бы оправдали.';
        $lines[] = 'Это критерий детектора, а не предложенная правка: если перевод несёт лицо иначе';
        $lines[] = '(глаголом, «свой»), строка — ложное срабатывание, и это видно прямо здесь.';
        $lines[] = '';
        $terms = array_values(array_filter($view->rows, static fn (TranslationKeyAuditRow $r): bool => $r->kind === 'term'));
        $examples = array_values(array_filter($view->rows, static fn (TranslationKeyAuditRow $r): bool => $r->kind === 'example'));

        $lines[] = '### Термины (' . count($terms) . ')';
        $lines[] = '';
        $lines[] = '| ← | язык | термин | текущий перевод | что не так | коллекция | группа |';
        $lines[] = '|---|---|---|---|---|---|---|';
        foreach ($terms as $row) {
            $lines[] = sprintf(
                '| **%s** | `%s` | %s | %s | %s | %s | `%s` |',
                strtoupper($row->direction),
                $row->lang,
                $this->cell($row->termText),
                $this->cell($row->translation),
                $this->cell($this->missing($row)),
                $this->cell($this->decks($row)),
                implode('`, `', $row->groups),
            );
        }
        $lines[] = '';

        $lines[] = '### Примеры (' . count($examples) . ')';
        $lines[] = '';
        if ($examples === []) {
            $lines[] = '_Чисто._';
            $lines[] = '';
        } else {
            $lines[] = '| ← | язык | термин | пример | перевод примера | что не так | коллекция | группа |';
            $lines[] = '|---|---|---|---|---|---|---|---|';
            foreach ($examples as $row) {
                $lines[] = sprintf(
                    '| **%s** | `%s` | %s | %s | %s | %s | %s | `%s` |',
                    strtoupper($row->direction),
                    $row->lang,
                    $this->cell($row->termText),
                    $this->cell($row->sourceText),
                    $this->cell($row->translation),
                    $this->cell($this->missing($row)),
                    $this->cell($this->decks($row)),
                    implode('`, `', $row->groups),
                );
            }
            $lines[] = '';
        }

        $lines[] = '## Разбивка по группам';
        $lines[] = '';
        $lines[] = '| группа | кандидатов |';
        $lines[] = '|---|---|';
        foreach ($this->byGroup($view->rows, $view->groupNames) as $group => $count) {
            $lines[] = "| `{$group}` | {$count} |";
        }
        $lines[] = '';

        $lines[] = '## Разбивка по коллекциям';
        $lines[] = '';
        $lines[] = 'Термин живёт в нескольких колодах сразу, поэтому сумма по строкам больше числа';
        $lines[] = 'кандидатов — это счётчик «сколько кандидатов спрашивает эта колода», а не дележ.';
        $lines[] = '';
        $lines[] = '| коллекция | кандидатов |';
        $lines[] = '|---|---|';
        foreach ($this->byCollection($view->rows) as $deck => $count) {
            $lines[] = sprintf('| %s | %d |', $this->cell($deck), $count);
        }
        $lines[] = '';

        return implode("\n", $lines) . "\n";
    }

    /** The decks a candidate is asked in, or a dash — a term outside every live deck is still a key. */
    private function decks(TranslationKeyAuditRow $row): string
    {
        return $row->collections === [] ? '—' : implode(', ', $row->collections);
    }

    /**
     * How many accepted forms a cell shows before it stops being evidence and starts being noise.
     *
     * The counterpart lists are whole paradigms — «вы» through «твоею» is 35 words — and a table
     * that prints all of them per row cannot be read at all. The first few say what the criterion
     * IS, which is the point; the count says how much was elided, so nobody mistakes the cell for
     * the whole list.
     */
    private const EXPECTED_SHOWN = 6;

    /**
     * «что не так», one entry per word: the word, then the rule's criterion for it.
     *
     * The arrow means different things per direction and the label says which, because a reader
     * skimming the column must not have to remember: LOST expects those forms in the TRANSLATION,
     * EXTRA expected one of those words in the SOURCE.
     */
    private function missing(TranslationKeyAuditRow $row): string
    {
        $parts = [];
        foreach ($row->words as $word) {
            $expected = $row->expectedForms[$word] ?? [];
            if ($expected === []) {
                $parts[] = "`{$word}`";

                continue;
            }
            $shown = implode('/', array_slice($expected, 0, self::EXPECTED_SHOWN));
            if (count($expected) > self::EXPECTED_SHOWN) {
                $shown .= '/… (' . count($expected) . ' форм)';
            }
            $parts[] = $row->direction === 'extra'
                ? "`{$word}` — нет в источнике: {$shown}"
                : "`{$word}` → {$shown}";
        }

        return $parts === [] ? '—' : implode('; ', $parts);
    }

    /** @param list<TranslationKeyAuditRow> $rows */
    private function countForDirection(array $rows, string $direction): int
    {
        return count(array_filter($rows, static fn (TranslationKeyAuditRow $r): bool => $r->direction === $direction));
    }

    /** @param list<TranslationKeyAuditRow> $rows */
    private function countForLang(array $rows, string $lang): int
    {
        return count(array_filter($rows, static fn (TranslationKeyAuditRow $r): bool => $r->lang === $lang));
    }

    /**
     * @param  list<TranslationKeyAuditRow>  $rows
     * @param  list<string>  $groupNames
     * @return array<string, int>  every group the rule knows, including the ones that found nothing
     */
    private function byGroup(array $rows, array $groupNames): array
    {
        $counts = array_fill_keys($groupNames, 0);
        foreach ($rows as $row) {
            foreach ($row->groups as $group) {
                $counts[$group] = ($counts[$group] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * @param  list<TranslationKeyAuditRow>  $rows
     * @return array<string, int>  deck title => candidates it asks, worst first
     */
    private function byCollection(array $rows): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $decks = $row->collections === [] ? ['— вне колод —'] : $row->collections;
            foreach ($decks as $deck) {
                $counts[$deck] = ($counts[$deck] ?? 0) + 1;
            }
        }
        // Worst deck first, ties by title: the reader wants the deck to fix, and a stable order
        // keeps two exports of the same store diffable.
        uksort($counts, static fn (string $a, string $b): int => [$counts[$b], $a] <=> [$counts[$a], $b]);

        return $counts;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    /** A pipe inside a cell would end the column early and silently shift every value after it. */
    private function cell(string $value): string
    {
        return str_replace(['|', "\n"], ['\\|', ' '], trim($value));
    }
}
