<?php

/**
 * FIX-4b — ПРИЁМКА Б: FIX-4's own acceptance A (`docs/research/fix-4/tools/replay-a.php`, word for word in what it
 * computes) run by WHICHEVER CODE the container carries — once by `main` (FIX-4), once by the branch (FIX-4b) — so the two
 * results are compared move by move (`compare-b.php`). The only changes: the code's root (`APP_ROOT`, default `/wt`) and
 * the output file (argv[1]) are named by the caller, and the table is not printed — the comparison prints it.
 *
 * What FIX-4's tool does, unchanged: the three talks of the owner's gym plan (2DX8QC) read again by the judge, move by
 * move, on their saved turns — no model, no voice. For every learner move: what the old judge wrote (`phrases_used`), and
 * what the judge makes of the same words (said · almost · extra) in the scene the move is read in; for every line of the
 * role: the door it named (`opens_target`) and whether the server takes it — the scene the line is said in, the target not
 * said yet (by the old credit and by the new one) — or drops it, and why.
 *
 * THE SCENE A MOVE OF THE REHEARSAL IS READ IN is not a fact of the journal: the old talk walked its scenes by the model's
 * `checkpoint_done` (the reception closed on line 9, after the receptionist had asked the trainer's question on line 7).
 * Three readings are reported side by side:
 *   - `acceptance` — the order's: the reception for moves 2–6, the trainer from move 8 (where the talk actually was);
 *   - `stored` — by the checkpoints the old talk marked (a scene is current until the line that marked it);
 *   - `rule` — the new server's own border on these moves: a scene closes when its targets are said or its moves are
 *     spent (targets + 1) — what a talk would have walked had it answered these words (it would not have: the role of
 *     v3.1 knows the reception's targets only, and would never have asked about experience on line 7).
 *
 * NOTHING IS WRITTEN to the base: the session is READ ONLY (checked below), no keys, no stray requests. Output: the JSON
 * file named by argv[1] (the shape of FIX-4's `live/acceptance-a.json`).
 *
 * Run (the branch, FIX-4b): docker exec -w /wt -e DB_DATABASE=wordtrainer -e PGOPTIONS='-c default_transaction_read_only=on' \
 *        -e LOG_CHANNEL=stderr -e CACHE_STORE=array -e QUEUE_CONNECTION=fix4b_none -e OPENAI_API_KEY= -e GEMINI_API_KEY= \
 *        -e ELEVENLABS_API_KEY= -e ANTHROPIC_API_KEY= wt_fix4b php docs/research/fix-4b/tools/replay-b.php \
 *        docs/research/fix-4b/live/replay-fix4b.json
 * Run (`main`, FIX-4): the same in a throwaway container of the `app` image with the main tree at /main
 *        (`docker compose run --rm --no-deps -v <main>/backend2:/main -v <branch>/backend2/docs/research/fix-4b:/fix4b
 *        -e APP_ROOT=/main … app php /fix4b/tools/replay-b.php /fix4b/live/replay-fix4.json`).
 */

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\ConversationMaterialView;
use App\Modules\Plan\Application\Service\ConversationMaterial;
use App\Modules\Plan\Application\Service\PlanAccess;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Service\FrameJudge;
use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;
use App\Modules\Plan\Domain\ValueObject\ConversationRejection;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

const PLAN = '01M32DX8QCABM348XP45Z1ZD4M';
const USER = '01M12HTZ1QHPNDZ5J8SPKB58QP';
/** talk id => day */
const TALKS = ['01M32FJ5FQNRNSQH6PNC7DP5E0' => 1, '01M34HXHP05JY8NVF1305NRY5N' => 2, '01M36X3W5QBB8TDYF3N3D519FG' => 3];
/** FIX-4's reading of the rehearsal: the scene (0 — reception, 1 — trainer) of each learner move. */
const ACCEPTANCE_SCENE = [2 => 0, 4 => 0, 6 => 0, 8 => 1, 10 => 1, 12 => 1, 14 => 1, 16 => 1, 18 => 1];

$root = rtrim(getenv('APP_ROOT') ?: '/wt', '/');
$output = $argv[1] ?? null;
if (! is_string($output) || $output === '') {
    fwrite(STDERR, "name the output file\n");
    exit(1);
}
require "{$root}/vendor/autoload.php";
$app = require "{$root}/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

DB::connection()->getPdo()->exec('SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY');
$session = DB::selectOne("select current_database() as db, current_setting('default_transaction_read_only') as d, current_setting('transaction_read_only') as t");
if ($session->db !== 'wordtrainer' || $session->d !== 'on' || $session->t !== 'on') {
    fwrite(STDERR, "not a read-only session of the live base — stop\n");
    exit(1);
}
foreach (['OPENAI_API_KEY', 'GEMINI_API_KEY', 'ELEVENLABS_API_KEY', 'ANTHROPIC_API_KEY'] as $key) {
    if ((string) env($key) !== '') {
        fwrite(STDERR, "{$key} is set — stop\n");
        exit(1);
    }
}
Http::preventStrayRequests();

$plan = app(PlanAccess::class)->owned(PlanId::fromString(PLAN), UserId::fromString(USER));
$judge = new FrameJudge;
$pack = app(LanguagePacks::class)->for('en');

/** `<scene>:<ref>` as the table writes it: `s1 p2` — the scene's place in the talk; a scene alone: `s1`. */
$name = static function (string $id, array $sceneIds): string {
    [$scene, $ref] = explode(':', $id, 2) + [1 => ''];
    $at = array_search($scene, $sceneIds, true);

    return trim(($at === false ? '?' : 's'.($at + 1)).' '.$ref);
};

/**
 * The new judge on one move in one scene: the constructions of the scene not said yet (by the new credit).
 *
 * @param  array<string, true>  $said
 * @return array{said: list<string>, almost: list<string>, extra: list<string>, values: array<string, string>}
 */
$read = static function (ConversationMaterialView $material, string $scene, string $heard, array $said) use ($judge, $pack): array {
    $frames = array_values(array_filter($material->phrasesOf($scene), static fn (ConversationPhrase $p): bool => ! isset($said[$p->id()])));
    $verdict = $judge->move($heard, $frames, $pack);

    return [
        'said' => array_values(array_filter($verdict->said, static fn (string $id): bool => $material->isTarget($id))),
        'almost' => $verdict->almost,
        'extra' => array_values(array_filter($verdict->said, static fn (string $id): bool => ! $material->isTarget($id))),
        'values' => $verdict->values,
    ];
};

/** Would the new server take the door a line named: the line's scene, the target not said. */
$door = static function (ConversationMaterialView $material, ?string $opens, string $scene, array $said): ?string {
    if ($opens === null || $opens === '') {
        return null;
    }
    $target = $material->phrase($opens);

    return match (true) {
        $target === null || ! $material->isTarget($opens) => ConversationRejection::UNKNOWN_ID,
        $target->sceneId !== $scene => ConversationRejection::FOREIGN_SCENE,
        isset($said[$opens]) => ConversationRejection::ALREADY_SAID,
        default => 'принято',
    };
};

$out = [];
foreach (TALKS as $talkId => $day) {
    $material = app(ConversationMaterial::class)->for($plan, $plan->day($day));
    $sceneIds = $material->sceneIds();
    $rows = DB::table('conversation_turns')->where('conversation_id', $talkId)->orderBy('turn_index')
        ->get(['turn_index', 'kind', 'text_target', 'phrases_used', 'opens_target', 'checkpoint_done']);

    // The three readings of the scene, walked together over the journal.
    $oldSaid = [];                                     // what the old judge had credited by then
    $said = ['acceptance' => [], 'stored' => [], 'rule' => []];
    $marked = [];                                      // checkpoints the old talk marked, line by line
    $ruleAt = 0;                                       // the scene the new rule stands in
    $ruleMoves = array_fill(0, count($sceneIds), 0);
    $lastScene = ['acceptance' => $sceneIds[0], 'stored' => $sceneIds[0], 'rule' => $sceneIds[0]];
    $turns = [];
    foreach ($rows as $row) {
        $index = (int) $row->turn_index;
        $stored = array_values(array_filter(json_decode((string) $row->phrases_used, true) ?: [], 'is_string'));
        $turn = ['index' => $index, 'kind' => $row->kind, 'text' => (string) $row->text_target];
        if ($row->kind === 'agent') {
            $opens = $row->opens_target === null ? null : (string) $row->opens_target;
            $turn['was'] = ['opens' => $opens === null ? null : $name($opens, $sceneIds), 'checkpoint' => $row->checkpoint_done === null ? null : $name(trim((string) $row->checkpoint_done), $sceneIds)];
            foreach (['acceptance', 'stored', 'rule'] as $reading) {
                $turn['now'][$reading] = [
                    'scene' => $name($lastScene[$reading], $sceneIds),
                    'door' => $door($material, $opens, $lastScene[$reading], $said[$reading]),
                    'door_by_old_credit' => $door($material, $opens, $lastScene[$reading], $oldSaid),
                ];
            }
            if ($row->checkpoint_done !== null) {
                $marked[] = trim((string) $row->checkpoint_done);
            }
            $turns[] = $turn;

            continue;
        }

        // A learner's move, in the scene each reading puts it in.
        $scenes = [
            'acceptance' => count($sceneIds) > 1 ? $sceneIds[ACCEPTANCE_SCENE[$index] ?? 0] : $sceneIds[0],
            'stored' => array_values(array_diff($sceneIds, $marked))[0] ?? $sceneIds[count($sceneIds) - 1],
            'rule' => $sceneIds[$ruleAt],
        ];
        $turn['was'] = ['phrases_used' => array_map(static fn (string $id): string => $name($id, $sceneIds), $stored)];
        foreach ($scenes as $reading => $scene) {
            $verdict = $row->kind === 'said' ? $read($material, $scene, (string) $row->text_target, $said[$reading]) : ['said' => [], 'almost' => [], 'extra' => [], 'values' => []];
            foreach ([...$verdict['said'], ...$verdict['extra']] as $id) {
                $said[$reading][$id] = true;
            }
            $turn['now'][$reading] = [
                'scene' => $name($scene, $sceneIds),
                'said' => array_map(static fn (string $id): string => $name($id, $sceneIds), $verdict['said']),
                'almost' => array_map(static fn (string $id): string => $name($id, $sceneIds), $verdict['almost']),
                'extra' => array_map(static fn (string $id): string => $name($id, $sceneIds), $verdict['extra']),
                'values' => $verdict['values'],
            ];
            $lastScene[$reading] = $scene;
        }
        foreach ($stored as $id) {
            $oldSaid[$id] = true;
        }
        // The new rule's border after the move: its targets said, or its moves spent.
        if (count($sceneIds) > 1) {
            $ruleMoves[$ruleAt]++;
            $targets = array_map(static fn (ConversationPhrase $t): string => $t->id(), $material->targetsOf($sceneIds[$ruleAt]));
            $allSaid = $targets !== [] && array_diff($targets, array_keys($said['rule'])) === [];
            if (($allSaid || $ruleMoves[$ruleAt] >= max(1, count($targets)) + 1) && $ruleAt < count($sceneIds) - 1) {
                $turn['now']['rule']['closes_scene'] = true;
                $ruleAt++;
                $lastScene['rule'] = $sceneIds[$ruleAt];
            }
        }
        $turns[] = $turn;
    }

    $out[] = [
        'talk' => $talkId,
        'day' => $day,
        'scenes' => $sceneIds,
        'targets' => array_map(static fn (ConversationPhrase $t): array => [
            'short_id' => $material->shortId($t), 'id' => $name($t->id(), $sceneIds), 'frame' => $t->frameTarget,
        ], $material->targets),
        'turns' => $turns,
    ];
}

file_put_contents($output, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
fwrite(STDERR, "wrote {$output}\n");
