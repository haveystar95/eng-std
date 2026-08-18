<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Presentation\Console;

use App\Modules\Shared\Infrastructure\Support\ExportHeader;
use App\Modules\Vocabulary\Application\Dto\TranslationKeyRow;
use App\Modules\Vocabulary\Application\Query\TranslationKeyReader;
use App\Modules\Vocabulary\Domain\Service\AddresseeIsomorphism;
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
 * design ({@see AddresseeIsomorphism}): Russian carries a person in ways it cannot see, so a hit is
 * a candidate for the owner to read, never a verdict. A heuristic this rough with a write path would
 * quietly rewrite the very content it was asked to audit; the accepted corrections go back through
 * the existing apply mechanism, as a separate, deliberate pass.
 */
final class AuditTranslationKeysCommand extends Command
{
    protected $signature = 'vocab:audit-translation-keys
        {--term-lang=en : the term side language}
        {--source-lang=ru : the learner side language}
        {--out= : write the export here (default storage/app/translation-keys-audit.md)}';

    protected $description = 'Аудит переводов-ключей: термин адресует кого-то, а перевод — нет. Только выгрузка.';

    public function handle(TranslationKeyReader $keys, AddresseeIsomorphism $rule): int
    {
        $termLang = $this->stringOption('term-lang') ?? 'en';
        $sourceLang = $this->stringOption('source-lang') ?? 'ru';
        $path = $this->stringOption('out') ?? storage_path('app/translation-keys-audit.md');

        $rows = $keys->primaryKeys($termLang, $sourceLang);

        /** @var list<array{row: TranslationKeyRow, groups: list<string>}> $candidates */
        $candidates = [];
        foreach ($rows as $row) {
            $groups = $rule->violations($row->termText, $row->translation);
            if ($groups !== []) {
                $candidates[] = ['row' => $row, 'groups' => $groups];
            }
        }

        $this->writeExport($path, $candidates, count($rows), $termLang, $sourceLang);

        $this->line("просмотрено пар: " . count($rows));
        $this->line("кандидатов на вычитку: " . count($candidates));

        $byGroup = array_fill_keys(AddresseeIsomorphism::groupNames(), 0);
        foreach ($candidates as $candidate) {
            foreach ($candidate['groups'] as $group) {
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

    /** @param list<array{row: TranslationKeyRow, groups: list<string>}> $candidates */
    private function writeExport(string $path, array $candidates, int $seen, string $termLang, string $sourceLang): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($path, $this->markdown($candidates, $seen, $termLang, $sourceLang));
    }

    /** @param list<array{row: TranslationKeyRow, groups: list<string>}> $candidates */
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
        foreach ($candidates as $candidate) {
            $row = $candidate['row'];
            $decks = $row->collections === [] ? '—' : implode(', ', $row->collections);
            $lines[] = sprintf(
                '| %s | %s | %s | `%s` |',
                $this->cell($row->termText),
                $this->cell($row->translation),
                $this->cell($decks),
                implode('`, `', $candidate['groups']),
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
