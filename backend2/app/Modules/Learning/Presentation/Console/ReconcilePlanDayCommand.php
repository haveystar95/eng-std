<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Console;

use App\Modules\Learning\Application\Service\PlanDayPassing;
use App\Modules\Learning\Application\Service\PlanProgress;
use App\Modules\Learning\Domain\Repository\PlanDayRepository;
use App\Modules\Learning\Domain\Repository\PlanRepository;
use App\Modules\Learning\Domain\Repository\PlanStagePassageRepository;
use App\Modules\Learning\Domain\Service\PlanDayPassage;
use App\Modules\Learning\Domain\ValueObject\PlanId;
use Illuminate\Console\Command;

/**
 * ПЕРЕСЧИТАТЬ СОСТОЯНИЕ ДНЯ ПО ЖУРНАЛУ — доменный путь для дня, застрявшего до наряда DAY-GATE-1.
 *
 * Живой прогон 07.09 оставил день, у которого по СТАРОМУ правилу выхода не было: три реплики
 * отвечены неверно, по правилу «один показ ступени в день» (считанному ПО КАРТОЧКЕ) сегодня им
 * больше ничего не причиталось, посадка была пуста, а день числился идущим. `learning_plan_days.status`
 * хранит вердикт, а не выводит его, — и написан он был старым кодом.
 *
 * ЧТО КОМАНДА ДЕЛАЕТ НА САМОМ ДЕЛЕ: пересчитывает и, если день ПРОЙДЕН по новому правилу, пишет это
 * в строку. Она НЕ «расклинивает» день сама по себе — расклинивают его решения 291–292, после
 * которых лестница снова должна карточкам то, что должна. На боевом плане владельца команда честно
 * оказалась пустой операцией: день 1 после правок стоит на `conversation=current` с двумя
 * недоданными ходами, то есть проходится дальше, а не закрыт задним числом.
 *
 * Команда не чинит данные руками и не умеет ничего, чего не умеет обычный путь: она считает то же
 * самое, что считает любой запрос плана ({@see PlanProgress}), и записывает вердикт тем же кодом,
 * что и конец посадки ({@see PlanDayPassing}). Никаких UPDATE мимо домена — ровно поэтому она и
 * существует вместо «поправить строку в базе».
 *
 * ## Что она делает с наряда DAY-GATE-1 (доработка)
 *
 * «Этап пройден» стало СОБЫТИЕМ в журнале
 * ({@see \App\Modules\Learning\Domain\Repository\PlanStagePassageRepository}), и у планов,
 * которые шли ДО этой правки, событий нет — есть только пересчёт, который к полуночи обнуляется.
 * Поэтому команда дописывает недостающие события: она считает то же, что считает любой запрос
 * плана, и записывает закрытые этапы тем же кодом, что и конец посадки. Отдельного «бэкфилла» с
 * прямыми UPDATE нет и не будет.
 *
 * Дата у дописанных событий — дата СВЕРКИ: она восстанавливает факт, а не время, и делать вид, что
 * знает время, ей нечем.
 *
 * Побочный эффект у неё ровно один и он законный: день, ставший `done`, ставит в очередь следующий
 * ({@see \App\Modules\Learning\Domain\Service\PlanGenerationPolicy::nextAfterDone()}), потому что
 * это одно и то же событие. `--dry-run` показывает расклад и не пишет ничего.
 */
final class ReconcilePlanDayCommand extends Command
{
    protected $signature = 'plan:reconcile-day {plan : ULID плана} {day? : номер дня; без него — все дни}
        {--dry-run : посчитать и показать, ничего не записывая}';

    protected $description = 'Пересчитать этапы дня плана по журналу и записать вердикт «пройден».';

    public function handle(
        PlanRepository $plans,
        PlanDayRepository $days,
        PlanProgress $progress,
        PlanDayPassing $passing,
        PlanStagePassageRepository $stagePassages,
    ): int {
        $plan = $this->argument('plan');
        $planId = is_string($plan) ? $plan : '';
        $plan = $plans->findById(PlanId::fromString($planId));
        if ($plan === null) {
            $this->error("План {$planId} не найден.");

            return self::FAILURE;
        }

        $only = $this->argument('day');
        $only = $only === null ? null : (int) $only;

        $planDays = $days->listForPlan($plan->id());
        $eventsBefore = self::countEvents($stagePassages->forPlan($plan->id()));
        $before = $progress->forPlan($plan, $planDays);

        $this->line('БЫЛО:');
        $this->table(
            ['день', 'статус строки', 'этапы', 'пройден'],
            $this->rows($planDays, $before, $only),
        );

        if ($this->option('dry-run')) {
            $this->comment('--dry-run: ничего не записано.');

            return self::SUCCESS;
        }

        // ТЕМ ЖЕ КОДОМ, ЧТО И КОНЕЦ ПОСАДКИ. Он идемпотентен по построению: день, уже помеченный
        // `done`, в список на отметку не попадает.
        $passing->mark($plan, $planDays, $before);

        $planDays = $days->listForPlan($plan->id());
        $log = $stagePassages->forPlan($plan->id());
        $after = $progress->forPlan($plan, $planDays);

        $this->line('СТАЛО:');
        $this->table(
            ['день', 'статус строки', 'этапы', 'пройден'],
            $this->rows($planDays, $after, $only),
        );

        // СКОЛЬКО СОБЫТИЙ ДОПИСАНО — главное число этой команды с наряда DAY-GATE-1: именно они
        // делают пройденное неотменяемым полуночью.
        $written = self::countEvents($log) - $eventsBefore;
        $this->line("Событий «этап пройден» дописано: {$written}.");
        foreach ($log as $index => $stages) {
            if ($only === null || $index === $only) {
                $this->line("  день {$index}: " . implode(' · ', $stages));
            }
        }

        return self::SUCCESS;
    }

    /** @param array<int, list<string>> $log */
    private static function countEvents(array $log): int
    {
        $n = 0;
        foreach ($log as $stages) {
            $n += count($stages);
        }

        return $n;
    }

    /**
     * @param  list<\App\Modules\Learning\Domain\Entity\PlanDay>  $planDays
     * @return list<array{0: int, 1: string, 2: string, 3: string}>
     */
    private function rows(array $planDays, \App\Modules\Learning\Application\Dto\PlanProgressView $progress, ?int $only): array
    {
        $rows = [];
        foreach ($planDays as $day) {
            $index = $day->dayIndex();
            if ($only !== null && $index !== $only) {
                continue;
            }
            $view = $progress->days[$index] ?? null;
            $stages = $view === null ? [] : $view->stages;
            $rows[] = [
                $index,
                $day->status()->value,
                implode(' · ', array_map(
                    static fn (array $row): string => $row['stage']->value . '=' . $row['state']->value,
                    $stages,
                )),
                self::verdictOf($view, $stages),
            ];
        }

        return $rows;
    }

    /**
     * «пройден» словом — и, если нет, какой этап сейчас.
     *
     * @param  list<array{stage: \App\Modules\Learning\Domain\ValueObject\PlanDayStage, state: \App\Modules\Learning\Domain\ValueObject\PlanDayStageState, cards: int}>  $stages
     */
    private static function verdictOf(?\App\Modules\Learning\Application\Dto\PlanDayProgressView $view, array $stages): string
    {
        if ($view === null) {
            return '—';
        }

        $current = PlanDayPassage::current($stages);

        return ($view->passed ? 'да' : 'нет')
            . ($current === null ? '' : ' (сейчас: ' . $current->value . ')');
    }
}
