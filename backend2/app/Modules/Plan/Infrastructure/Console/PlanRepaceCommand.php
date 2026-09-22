<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Console;

use App\Modules\Plan\Application\Query\GetDayRoom;
use App\Modules\Plan\Application\Query\GetDayRoomHandler;
use App\Modules\Plan\Application\Service\PlanPaces;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\DayStatus;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanStatus;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * `plan:repace {--all} {--plan=*} {--dry}` — THE PLAN'S PRICE LIST TAKEN AGAIN (наряд FIX-3 §2).
 *
 * A plan keeps the price list of its days it was made with (`plans.pace`, {@see PlanPaces}); the list itself lives in
 * the config and was measured on the phone. This gives every plan named — `--all`: every plan that is not deleted — the
 * list the config has NOW, and prints, for every day of it that is not closed, the minutes the day window says before and
 * after («≈ N мин», the same reckoning the phone is shown, read through the day room). Idempotent: a plan that already
 * has the config's list is not written, and a second run writes nothing. `--dry` writes nothing and prints the same.
 *
 * Writes `plans.pace` only, one column by primary key — take the database backup first, as for any write to the dev
 * database. A day already dealt keeps its cards: the list changes what a day is reckoned to take, and the ceiling of the
 * «Фразы» of the days dealt from now on.
 */
final class PlanRepaceCommand extends Command
{
    protected $signature = 'plan:repace {--all : every plan that is not deleted} {--plan=* : only these plan ids} {--dry : say what would change, write nothing}';

    protected $description = 'Give plans the price list of their days the config has now, and print the minutes of their open days before and after';

    public function handle(PlanRepository $plans, PlanPaces $paces, GetDayRoomHandler $room): int
    {
        /** @var list<string> $named */
        $named = array_values(array_filter((array) $this->option('plan'), static fn (mixed $id): bool => is_string($id) && $id !== ''));
        foreach ($named as $id) {
            if (! Ulid::isValid($id)) {
                $this->error("Not a plan id: {$id}");

                return self::FAILURE;
            }
        }
        if ($named === [] && $this->option('all') !== true) {
            $this->error('Name the plans (--plan=<id>) or ask for every one of them (--all).');

            return self::FAILURE;
        }
        $dry = $this->option('dry') === true;
        $ids = $named !== [] ? $named : array_map('strval', DB::table('plans')
            ->where('status', '<>', PlanStatus::Deleted->value)
            ->orderBy('created_at')
            ->pluck('id')
            ->all());

        $current = $paces->current();
        $written = 0;
        $same = 0;
        foreach ($ids as $id) {
            $plan = $plans->findById(PlanId::fromString($id));
            if ($plan === null) {
                $this->warn("план {$id} — не найден");

                continue;
            }
            if (! $plan->repace($current)) {
                $same++;
                $this->line("план {$id} — прейскурант уже нынешний, не тронут");

                continue;
            }
            // Before and after are read by the day room itself, the one reckoning the phone is shown; a dry run writes the
            // list inside a transaction it rolls back, so «after» is what the window WOULD say.
            $before = $this->openDayMinutes($plan, $room);
            DB::beginTransaction();
            try {
                $plans->savePace($plan->id(), $current);
                $after = $this->openDayMinutes($plan, $room);
            } catch (Throwable $e) {
                DB::rollBack();

                throw $e;
            }
            if ($dry) {
                DB::rollBack();
            } else {
                DB::commit();
                $written++;
            }
            foreach ($before as $number => $minutes) {
                $this->line(sprintf('%sплан %s · день %d: %s → %s мин', $dry ? '[--dry] ' : '', $id, $number, $minutes ?? '—', $after[$number] ?? '—'));
            }
            if ($before === []) {
                $this->line(($dry ? '[--dry] ' : '')."план {$id} — открытых дней нет");
            }
        }
        $this->info(sprintf('планов: %d · записано: %d · уже с нынешним прейскурантом: %d%s', count($ids), $written, $same, $dry ? ' (--dry: ничего не записано)' : ''));

        return self::SUCCESS;
    }

    /**
     * «≈ N мин» of every day of the plan that is not closed, as its window says it.
     *
     * @return array<int, int|null> day number → minutes (null: the window names none — a day with no cards yet)
     */
    private function openDayMinutes(Plan $plan, GetDayRoomHandler $room): array
    {
        $out = [];
        foreach ($plan->days() as $day) {
            if ($day->status() !== DayStatus::Closed) {
                $out[$day->number()] = $room(new GetDayRoom($plan->id(), $day->number(), $plan->userId()))->window->day->minutesEstimate;
            }
        }

        return $out;
    }
}
