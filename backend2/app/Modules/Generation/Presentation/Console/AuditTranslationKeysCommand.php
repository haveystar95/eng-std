<?php

declare(strict_types=1);

namespace App\Modules\Generation\Presentation\Console;

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
 * It writes an export and NOTHING ELSE — no `--apply`, deliberately. The detector is coarse by
 * design (Vocabulary's `AddresseeIsomorphism`): Russian carries a person in ways it cannot see, so a
 * hit is a candidate for the owner to read, never a verdict. A heuristic this rough with a write
 * path would quietly rewrite the very content it was asked to audit; the accepted corrections go
 * back through the existing apply mechanism, as a separate, deliberate pass.
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
        {--source-lang=ru : the learner side language}
        {--out= : write the export here (default storage/app/translation-keys-audit.md)}';

    protected $description = 'Аудит переводов-ключей: термин адресует кого-то, а перевод — нет. Только выгрузка.';

    public function handle(GetTranslationKeyAuditHandler $audit): int
    {
        $termLang = $this->stringOption('term-lang') ?? 'en';
        $sourceLang = $this->stringOption('source-lang') ?? 'ru';
        $path = $this->stringOption('out') ?? storage_path('app/translation-keys-audit.md');

        $view = $audit(new GetTranslationKeyAudit($termLang, $sourceLang));

        $this->writeExport($path, $view->rows, $view->seen, $termLang, $sourceLang);

        $this->line('просмотрено пар: ' . $view->seen);
        $this->line('кандидатов на вычитку: ' . count($view->rows));

        $byGroup = array_fill_keys($view->groupNames, 0);
        foreach ($view->rows as $row) {
            foreach ($row->groups as $group) {
                $byGroup[$group]++;
            }
        }
        $this->table(
            ['группа', 'кандидатов'],
            array_map(static fn (string $g, int $n): array => [$g, (string) $n], array_keys($byGroup), $byGroup),
        );
        $this->info("выгрузка: {$path}");

        return self::SUCCESS;
    }

    /** @param list<\App\Modules\Generation\Application\Dto\TranslationKeyAuditRow> $candidates */
    private function writeExport(string $path, array $candidates, int $seen, string $termLang, string $sourceLang): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($path, $this->markdown($candidates, $seen, $termLang, $sourceLang));
    }

    /** @param list<\App\Modules\Generation\Application\Dto\TranslationKeyAuditRow> $candidates */
    private function markdown(array $candidates, int $seen, string $termLang, string $sourceLang): string
    {
        $header = ExportHeader::now();

        $lines = [
            $header->comment(),
            '# Переводы-ключи на вычитку',
            '',
            $header->line("направление: `{$termLang}` → `{$sourceLang}`"),
            '',
            'Здесь то, где термин к кому-то ОБРАЩАЕТСЯ, а перевод этого не несёт: по такому переводу',
            'нельзя однозначно восстановить термин, и честный ответ уходит в лог как промах.',
            '',
            '**Детектор грубый и ничего не правит.** Русский выражает лицо способами, которых он не',
            'видит: «расскажите» уже адресовано, родительный может подразумеваться, «свой» стоит за',
            '«ваш». Строка здесь — кандидат на прочтение, а не приговор. Правки едут отдельным заходом',
            'через существующий apply-механизм.',
            '',
            "Просмотрено пар: **{$seen}**. Кандидатов: **" . count($candidates) . '**.',
            '',
        ];

        if ($candidates === []) {
            $lines[] = '_Нечего вычитывать._';
            $lines[] = '';

            return implode("\n", $lines) . "\n";
        }

        $lines[] = '| термин | текущий перевод | коллекция | группа-нарушение |';
        $lines[] = '|---|---|---|---|';
        foreach ($candidates as $row) {
            $decks = $row->collections === [] ? '—' : implode(', ', $row->collections);
            $lines[] = sprintf(
                '| %s | %s | %s | `%s` |',
                $this->cell($row->termText),
                $this->cell($row->translation),
                $this->cell($decks),
                implode('`, `', $row->groups),
            );
        }
        $lines[] = '';

        return implode("\n", $lines) . "\n";
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
