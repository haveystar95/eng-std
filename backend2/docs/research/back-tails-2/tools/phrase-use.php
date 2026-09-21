<?php

declare(strict_types=1);

/**
 * BACK-TAILS-2 §2 · «СКАЖИ В РАЗГОВОРЕ» ON A REAL TALK — the old rule against the new one (report §1.2, the table).
 *
 *   docker exec -e DB_DATABASE=wordtrainer wt_tails2 \
 *     php docs/research/back-tails-2/tools/phrase-use.php <conversation-id> docs/research/conv-2/den/model-calls.json
 *
 * The talk's targets are read as the server lists them (`ConversationMaterial` over the plan in the database) and its
 * learner moves from `conversation_turns`; the role's `phrases_used` for each move — from an export of its answers (the
 * `HEARD` line of the prompt names the move it answered). Every target is then ticked twice:
 *
 *   old — the rule before the наряд, `SpeechMatch` in its `free` mode over the frame's key (the body of the removed
 *         `SpokenPhrases::heardIn()`, inline), over every phrase of the talk's scenes;
 *   new — `PhraseUse`, move by move over the targets not said yet, the role's word as the second support.
 *
 * The session is READ ONLY: it reads the live database and must not be able to write to it.
 */

use App\Modules\Plan\Application\Service\ConversationMaterial;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Service\PhraseUse;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\Service\SpeechMatch;
use App\Modules\Shared\Domain\ValueObject\SpeechMode;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$root = getenv('APP_ROOT') ?: dirname(__DIR__, 4);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

DB::statement('SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY');

[$talkId, $export] = [$argv[1] ?? '', $argv[2] ?? ''];
$talk = DB::table('conversations')->where('id', $talkId)->first();
if ($talk === null) {
    fwrite(STDERR, "нет разговора {$talkId}\n");
    exit(1);
}
$plan = app(PlanRepository::class)->findById(PlanId::fromString((string) $talk->plan_id));
$day = $plan->day((int) $talk->day_number);
$material = app(ConversationMaterial::class)->for($plan, $day);
$pack = app(LanguagePacks::class)->for($plan->targetLang()->value);

// The role's word on each move, by the move it answered.
$modelSays = [];
if ($export !== '' && is_file($export)) {
    foreach (json_decode((string) file_get_contents($export), true, flags: JSON_THROW_ON_ERROR) as $call) {
        if (preg_match('/^HEARD[^:]*: (.*)$/m', (string) $call['user'], $m) === 1 && is_array($call['answer']['phrases_used'] ?? null)) {
            $modelSays[trim($m[1])] = $call['answer']['phrases_used'];
        }
    }
}

$moves = DB::table('conversation_turns')->where('conversation_id', $talkId)->where('speaker', 'learner')->where('kind', 'said')
    ->orderBy('turn_index')->get(['turn_index', 'text_target', 'phrases_used']);

$speech = new SpeechMatch;
$use = new PhraseUse;
$old = [];
$new = [];
$named = [];
foreach ($moves as $move) {
    $heard = (string) $move->text_target;
    foreach ($material->phrases as $phrase) {
        if (trim($phrase->key) !== '' && $speech->said($heard, $phrase->key, SpeechMode::Free, $pack->speech())) {
            $old[$phrase->id()] ??= $heard;
        }
    }
    $unsaid = array_values(array_filter($material->targets, static fn ($t): bool => ! isset($new[$t->id()])));
    $says = $modelSays[$heard] ?? [];
    foreach ($says as $id) {
        $named[$id][] = $heard;
    }
    foreach ($use->heardIn($heard, $unsaid, $says, $pack) as $id) {
        $new[$id] = $heard;
    }
}

printf("разговор %s · план %s · день %d · %s · целей %d · ходов ученика %d · база %s\n",
    $talkId, $talk->plan_id, $talk->day_number, $talk->type, count($material->targets), count($moves), (string) config('database.connections.pgsql.database'));
echo "ходы ученика:\n";
foreach ($moves as $move) {
    printf("  %2d «%s» · роль назвала: %s · сервер тогда записал: %s\n", $move->turn_index, $move->text_target,
        implode(', ', array_map(static fn (string $id): string => explode(':', $id)[1] ?? $id, $modelSays[$move->text_target] ?? [])) ?: '—',
        implode(', ', array_map(static fn (string $id): string => explode(':', $id)[1] ?? $id, json_decode((string) $move->phrases_used, true) ?: [])) ?: '—');
}
// Over several scenes a ref repeats: `s2:p1` is the second scene's p1.
$sceneNo = array_flip(array_map(static fn ($c): string => $c->sceneId, $material->checkpoints));
$name = static fn ($t): string => count($sceneNo) > 1 ? 's'.(($sceneNo[$t->sceneId] ?? 0) + 1).':'.$t->ref : $t->ref;

echo "\n| цель | ключ (старое правило) | где услышано | было | стало | почему «стало» |\n|---|---|---|---|---|---|\n";
foreach ($material->targets as $target) {
    $id = $target->id();
    $heard = $new[$id] ?? $old[$id] ?? null;
    $why = '—';
    if (isset($new[$id])) {
        ['found' => $found, 'total' => $total] = $use->tally($new[$id], $target->textTarget, $pack);
        $why = $use->said($new[$id], $target->textTarget, $pack)
            ? "по правилу: ключевых слов {$found} из {$total}"
            : "слово роли + {$found} из {$total} ключевых (≥ ½)";
    } elseif (isset($named[$id])) {
        $tallies = array_map(static function (string $h) use ($use, $target, $pack): string {
            ['found' => $f, 'total' => $t] = $use->tally($h, $target->textTarget, $pack);

            return "{$f} из {$t}";
        }, $named[$id]);
        $why = 'роль назвала, но ключевых слов '.implode(', ', $tallies).' (< ½)';
    }
    printf("| %s «%s» | %s | %s | %s | %s | %s |\n", $name($target), $target->textTarget, $target->key,
        $heard === null ? '—' : "«{$heard}»", isset($old[$id]) ? 'said' : '—', isset($new[$id]) ? 'said' : '—', $why);
}
printf("\nбыло %d из %d · стало %d из %d\n", count(array_intersect_key($old, array_flip(array_map(static fn ($t): string => $t->id(), $material->targets)))), count($material->targets), count($new), count($material->targets));
