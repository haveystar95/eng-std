<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Console;

use App\Modules\Plan\Domain\ValueObject\CardKind;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * ONE SCENE, ONE NAME — FOR THE DAYS DEALT BEFORE IT WAS SO (наряд BACK-TAILS-2 §6).
 *
 * A scene is named by its plan (`plan_scenes.title_native` / `title_target`): the route, the window, the talk. The sheet
 * of «Вспомнить» (`recall_scenes`) used to take the lesson's own title for it instead — the owner's plan said «Приём у
 * врача» everywhere and «У врача с сыном» on the sheet — and a dealt day keeps the payload it was dealt with. This command
 * finds the dealt sheets whose names are not their plan's and prints them, plan by plan and day by day, «было → стало»;
 * with `--apply` it writes the plan's names in and nothing else of the card. Idempotent: a second run finds nothing.
 *
 * Without `--apply` it writes nothing. With it — take the database backup first, as for any write to the dev database.
 */
final class PlanReconcileScenesCommand extends Command
{
    protected $signature = 'plan:reconcile-scenes {--apply : write the plan\'s names into the dealt cards}';

    protected $description = 'Name the scenes of dealt «Вспомнить» sheets by their plan, not by the lesson\'s own title';

    public function handle(): int
    {
        $apply = $this->option('apply') === true;
        $before = $this->mismatched();

        $fixed = 0;
        foreach ($before as $card) {
            foreach ($card['changes'] as $change) {
                $this->line(sprintf(
                    '%sплан %s · день %d · сцена %s: «%s» → «%s» · «%s» → «%s»',
                    $apply ? '' : '[dry-run] ',
                    $card['plan_id'], $card['day'], $change['scene_id'],
                    $change['was_native'], $change['now_native'], $change['was_target'], $change['now_target'],
                ));
            }
            if ($apply) {
                DB::table('day_cards')->where('id', $card['id'])->update([
                    'payload' => json_encode($card['payload'], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR),
                ]);
                $fixed++;
            }
        }

        $after = $apply ? count($this->mismatched()) : count($before);
        $this->info('карточек «Вспомнить» с именем сцены не из плана: было '.count($before)." / стало {$after}");
        if ($apply) {
            $this->info("исправлено карточек: {$fixed}");
        }

        return self::SUCCESS;
    }

    /**
     * The dealt sheets whose scenes are named otherwise than their plan names them, each with its payload as it SHOULD
     * read and what changes in it.
     *
     * @return list<array{id: string, plan_id: string, day: int, payload: array<string, mixed>, changes: list<array{scene_id: string, was_native: string, now_native: string, was_target: string, now_target: string}>}>
     */
    private function mismatched(): array
    {
        $cards = DB::table('day_cards as c')
            ->join('plan_days as d', 'd.id', '=', 'c.day_id')
            ->where('c.kind', CardKind::RecallScenes->value)
            ->orderBy('d.plan_id')->orderBy('d.number')->orderBy('c.id')
            ->get(['c.id', 'c.payload', 'd.plan_id', 'd.number']);

        $decoded = [];
        $sceneIds = [];
        foreach ($cards as $card) {
            $payload = json_decode((string) $card->payload, true);
            if (! is_array($payload) || ! is_array($payload['scenes'] ?? null)) {
                continue;
            }
            $decoded[] = [$card, $payload];
            foreach ($payload['scenes'] as $scene) {
                if (is_array($scene) && is_string($scene['scene_id'] ?? null)) {
                    $sceneIds[$scene['scene_id']] = true;
                }
            }
        }
        $names = DB::table('plan_scenes')->whereIn('id', array_keys($sceneIds))->get(['id', 'title_native', 'title_target'])->keyBy('id');

        $out = [];
        foreach ($decoded as [$card, $payload]) {
            $changes = [];
            foreach ($payload['scenes'] as $i => $scene) {
                $plan = is_array($scene) ? $names->get((string) ($scene['scene_id'] ?? '')) : null;
                if ($plan === null) {
                    continue;
                }
                $wasNative = (string) ($scene['title_native'] ?? '');
                $wasTarget = (string) ($scene['title_target'] ?? '');
                if ($wasNative === (string) $plan->title_native && $wasTarget === (string) $plan->title_target) {
                    continue;
                }
                $payload['scenes'][$i]['title_native'] = (string) $plan->title_native;
                $payload['scenes'][$i]['title_target'] = (string) $plan->title_target;
                $changes[] = [
                    'scene_id' => (string) $scene['scene_id'],
                    'was_native' => $wasNative, 'now_native' => (string) $plan->title_native,
                    'was_target' => $wasTarget, 'now_target' => (string) $plan->title_target,
                ];
            }
            if ($changes !== []) {
                $out[] = ['id' => (string) $card->id, 'plan_id' => (string) $card->plan_id, 'day' => (int) $card->number, 'payload' => $payload, 'changes' => $changes];
            }
        }

        return $out;
    }
}
