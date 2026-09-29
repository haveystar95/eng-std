<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Console;

use App\Modules\Plan\Domain\Service\PlanCalendar;
use App\Modules\Plan\Domain\ValueObject\DayType;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * A LOAD OF PLANS FOR EXPLAIN: N plans of six days for one learner, every scene with a lesson row,
 * every day dealt with about 67 cards — the shape of a real plan, without a model call. The
 * numbers the наряд asks for (≥50 plans, 300 days, 20 000 cards) are the defaults.
 *
 * Refused on the main database: this is a measuring rig, not content.
 */
final class PlanSeedLoadCommand extends Command
{
    protected $signature = 'plan:seed-load {user : owner user id} {--plans=50} {--cards-per-day=67}';

    protected $description = 'Seed a synthetic plan load (plans, days, scenes, cards, terms) for query analysis';

    /** The kinds of the session registry (SESSION-1a) the load cycles through, per stage. */
    private const KINDS = [
        'words' => ['word_intro', 'word_repeat', 'word_choose'],
        'phrases' => ['phrase_intro', 'phrase_slot', 'phrase_repeat'],
        'dialogue' => ['dialogue_partner', 'dialogue_answer'],
        'listen' => ['listen_dialogue', 'listen_question'],
        'speak' => ['speak_answer'],
    ];

    /** What each stage's cards are about: a word, a frame, an exchange — the listening is the day's. */
    private const UNIT_KINDS = [
        'words' => 'word',
        'phrases' => 'phrase',
        'dialogue' => 'exchange',
        'listen' => 'day',
        'speak' => 'exchange',
    ];

    public function handle(): int
    {
        $database = (string) config('database.connections.'.config('database.default').'.database');
        if ($database === 'wordtrainer') {
            $this->error('Refusing on the main database.');

            return self::FAILURE;
        }
        $userId = is_string($this->argument('user')) ? $this->argument('user') : '';
        $plans = max(1, (int) $this->option('plans'));
        $perDay = max(5, (int) $this->option('cards-per-day'));
        $now = now();
        $daysTotal = 6;
        $created = ['plans' => 0, 'days' => 0, 'cards' => 0, 'terms' => 0];
        // A real lesson shape: the mapper re-parses every stored lesson, and a stub would be a 500.
        $lessonJson = json_encode(FakePlanModel::lessonPayload(FakePlanModel::lessonRequest('Сцена')), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        // One live plan per learner is a unique index; the seed's first plan is the live one only
        // when the learner has none.
        $hasActive = DB::table('plans')->where('user_id', $userId)->where('status', 'active')->exists();
        for ($p = 0; $p < $plans; $p++) {
            $planId = Ulid::generate();
            $status = $p === 0 && ! $hasActive ? 'active' : ($p % 5 === 0 ? 'finished' : 'ready');
            DB::table('plans')->insert([
                'id' => $planId, 'user_id' => $userId, 'goal_text' => "load plan {$p}", 'target_lang' => 'en',
                'native_lang' => 'ru', 'level' => 'beginner', 'days_total' => $daysTotal, 'days_requested' => $daysTotal,
                'status' => $status, 'title_native' => "План {$p}", 'title_target' => "Plan {$p}",
                'event_native' => 'Приём', 'until_phrase_native' => 'До приёма', 'overdue_native' => 'Приём был вчера',
                'prompt_version_plan' => 'plan-builder-v2', 'build_version' => 'seed', 'model_plan' => 'seed',
                'cost_usd_plan' => '0.010000', 'checks_json' => '[]', 'started_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $created['plans']++;

            $sceneIds = [];
            $layout = PlanCalendar::layout($daysTotal);
            $order = 0;
            foreach ($layout as $index => $type) {
                $dayId = Ulid::generate();
                $sceneId = null;
                if ($type === DayType::Scene) {
                    $order++;
                    $sceneId = Ulid::generate();
                    $sceneIds[] = $sceneId;
                    DB::table('plan_scenes')->insert([
                        'id' => $sceneId, 'plan_id' => $planId, 'user_id' => $userId, 'order' => $order, 'kind' => 'situation',
                        'priority' => $order, 'title_native' => "Сцена {$order}", 'title_target' => "Scene {$order}",
                        'teaches_native' => 'описать боль', 'goals_native' => '["a","b","c"]', 'learner_role_target' => 'Parent',
                        'learner_role_native' => 'Родитель', 'partner_role_target' => 'Doctor', 'partner_role_native' => 'Врач',
                        'topic_description' => 'Situation: x. Learner: y. Partner: z. Learner must be able to: a. Partner will: b. Not in this scene: c.',
                        'image_prompt' => 'clinic', 'lesson_json' => $lessonJson, 'lesson_status' => 'ready',
                        'prompt_version_lesson' => 'lesson_day.v4.5', 'build_version' => 'seed', 'model_lesson' => 'seed',
                        'cost_usd_lesson' => '0.050000', 'checks_json' => '[]', 'generated_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                    $terms = [];
                    for ($t = 0; $t < 14; $t++) {
                        // A phrase carries its frame and slot, a word the cards it stands in — the frame columns (GEN-2a).
                        $phrase = $t >= 8;
                        $terms[] = [
                            'id' => Ulid::generate(), 'scene_id' => $sceneId, 'user_id' => $userId,
                            'kind' => $phrase ? 'phrase' : 'word', 'ref' => ($phrase ? 'p'.($t - 7) : 'v'.($t + 1)),
                            'position' => $t, 'text_target' => "term {$t}", 'text_native' => "термин {$t}",
                            'simplified_variants' => '[]', 'created_at' => $now, 'updated_at' => $now,
                            'frame_target' => $phrase ? 'I have ___.' : null, 'frame_native' => $phrase ? 'У меня ___.' : null,
                            'frame_pronunciation_native' => $phrase ? 'ай хэв ___' : null, 'frame_kind' => $phrase ? 'answer' : null,
                            'slot' => $phrase ? json_encode(['hint_native' => 'что', 'fillers' => [
                                ['target' => "term {$t}", 'native' => "термин {$t}", 'pronunciation_native' => 'тёрм', 'in_dialogue' => true],
                                ['target' => 'a cough', 'native' => 'кашель', 'pronunciation_native' => 'э коф', 'in_dialogue' => false],
                            ]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : null,
                            'used_in' => $phrase ? null : json_encode(['p1'], JSON_THROW_ON_ERROR),
                        ];
                    }
                    DB::table('plan_terms')->insert($terms);
                    $created['terms'] += count($terms);
                }
                $dayStatus = $index === 0 ? 'closed' : ($index === 1 ? 'in_progress' : 'locked');
                DB::table('plan_days')->insert([
                    'id' => $dayId, 'plan_id' => $planId, 'user_id' => $userId, 'number' => $index + 1, 'type' => $type->value,
                    'scene_id' => $sceneId, 'status' => $dayStatus, 'opens_on' => $now->copy()->addDays($index)->toDateString(),
                    'cards_total' => $perDay, 'cards_done' => $index === 0 ? $perDay : 0, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $created['days']++;

                $cards = [];
                $position = [];
                for ($c = 0; $c < $perDay; $c++) {
                    $stage = array_keys(self::KINDS)[$c % 5];
                    $kind = self::KINDS[$stage][$c % count(self::KINDS[$stage])];
                    $position[$stage] = ($position[$stage] ?? 0) + 1;
                    $answered = $index === 0;
                    $cardScene = $sceneId ?? $sceneIds[0] ?? '';
                    // The minimal payload: the readers find a card's term and lines by its scene and its unit.
                    $payload = ['scene_id' => $cardScene];
                    $unitRef = match ($stage) {
                        'words' => 'v'.(($position[$stage] - 1) % 8 + 1),
                        'phrases' => 'p'.(($position[$stage] - 1) % 6 + 1),
                        'listen' => $kind === 'listen_question' ? 'L'.(($position[$stage] - 1) % 3 + 1) : 'day',
                        default => 'x'.(($position[$stage] - 1) % 8 + 1),
                    };
                    $cards[] = [
                        'id' => Ulid::generate(), 'day_id' => $dayId, 'user_id' => $userId, 'stage' => $stage,
                        'position' => $position[$stage], 'kind' => $kind,
                        'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                        'source' => 'today', 'unit_kind' => self::UNIT_KINDS[$stage],
                        'unit_ref' => $unitRef, 'result' => $answered ? ($c % 9 === 0 ? 'failed' : 'passed') : null,
                        'attempts' => $answered ? 1 : 0, 'answered_at' => $answered ? $now : null,
                        'returns' => $answered && $c % 9 === 0 && $stage !== 'listen', 'created_at' => $now, 'updated_at' => $now,
                    ];
                }
                DB::table('day_cards')->insert($cards);
                $created['cards'] += count($cards);
            }
        }

        $this->info(sprintf('Seeded %d plans, %d days, %d cards, %d terms for user %s.', $created['plans'], $created['days'], $created['cards'], $created['terms'], $userId));

        return self::SUCCESS;
    }
}
