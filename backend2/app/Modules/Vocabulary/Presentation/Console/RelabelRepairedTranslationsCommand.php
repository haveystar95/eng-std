<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Presentation\Console;

use App\Modules\Vocabulary\Application\Command\RelabelRepairedTranslations;
use App\Modules\Vocabulary\Application\Command\RelabelRepairedTranslationsHandler;
use Illuminate\Console\Command;

/**
 * Dry by default — `--apply` is what writes. Same shape and same reason as
 * {@see \App\Modules\Generation\Presentation\Console\AuditDistractorsCommand}: a sweep that exists
 * only in a terminal history is a sweep nobody can re-run or verify.
 */
final class RelabelRepairedTranslationsCommand extends Command
{
    protected $signature = 'vocab:relabel-repaired-translations
        {--lang=ru : the learner language whose label is missing from already-repaired rows}
        {--apply : write the labels (default is a dry run)}
        {--v-kept : print every row that was deliberately LEFT alone, with the reason}';

    protected $description = 'Проставить lang строкам перевода, чей текст починка уже переписала (метку не трогала)';

    public function handle(RelabelRepairedTranslationsHandler $relabel): int
    {
        $apply = (bool) $this->option('apply');
        $langOption = $this->option('lang');
        $lang = is_string($langOption) && $langOption !== '' ? $langOption : 'ru';

        $this->info($apply ? "Перемаркировка на «{$lang}»: ПРИМЕНЯЮ" : "Перемаркировка на «{$lang}»: сухой прогон");

        $outcome = $relabel(new RelabelRepairedTranslations($lang, $apply));

        $byLang = [];
        foreach ($outcome->relabelled as $row) {
            $byLang[$row['from']] = ($byLang[$row['from']] ?? 0) + 1;
        }
        ksort($byLang);

        $rows = [];
        foreach ($byLang as $from => $n) {
            $rows[] = ["{$from} → {$lang}", (string) $n];
        }
        $rows[] = ['<options=bold>итого перемаркировано</>', "<options=bold>{$outcome->relabelledCount()}</>"];
        $rows[] = ['оставлено как есть', (string) $outcome->keptCount()];
        $this->table(['переход', 'строк'], $rows);

        if ((bool) $this->option('v-kept')) {
            foreach ($outcome->kept as $row) {
                $this->line("  · [{$row['lang']}] «{$row['term']}» → «{$row['text']}» — {$row['why']}");
            }
        }

        if (! $apply && $outcome->relabelledCount() > 0) {
            $this->comment('Ничего не записано. Повторить с --apply.');
        }

        return self::SUCCESS;
    }
}
