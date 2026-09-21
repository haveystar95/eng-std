<?php

declare(strict_types=1);

/**
 * CONV-2 · THE OWNER'S TALKS OF 21.09, ASKED AGAIN ON THE SAME INPUTS (report §1.1, §2.1).
 *
 * `den/model-calls.json` holds every call the role got in the two talks on the owner's phone — the rehearsal «Звонок
 * агенту» + «Просмотр жилья» and the gym day 1 — with the user message exactly as it was sent and what `conversation_agent.v1`
 * answered. This harness sends each of those messages again, to `conversation_agent.v2`: the SAME message, changed only
 * where v2 changes it — the CHECKPOINTS block, which now shows each exchange of the prepared visit with both sides named
 * (`LEARNER asks: … → YOU answer: …`, the role's line taken from the scene's lesson, `den/scenes.json`). The history in
 * the later calls is the history of the talk as it happened — with the flipped lines in it, and the owner answering as the
 * agent — so every call asks «given what was said so far, does the role hold its part now».
 *
 * Each answer is read by the server's guard ({@see RoleLines}); an answer that says a line of the learner is asked for
 * once more with REDO, as `ConversationMoves` does. Prints было / стало per call and the money; writes
 * `live/replay-den.json`.
 *
 *   docker exec wt_conv2_e2e php docs/research/conv-2/tools/replay-den.php
 *
 * Only on the disposable e2e database (the outbound log is written there); buys ≈ $0.001 a call.
 */

use App\Modules\Generation\Application\Dto\RenderedPrompt;
use App\Modules\Generation\Application\Port\ContentModelCatalog;
use App\Modules\Generation\Domain\ValueObject\PromptShape;
use App\Modules\Generation\Domain\ValueObject\ProviderId;
use App\Modules\Plan\Domain\Service\RoleLines;
use App\Modules\Plan\Infrastructure\Model\ContentModelPlanBuilder;
use App\Modules\Plan\Infrastructure\Prompt\PlanPromptFiles;
use App\Modules\Plan\Infrastructure\Prompt\PlanSchemas;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = (string) config('database.connections.pgsql.database');
if ($database !== 'wordtrainer_e2e_test') {
    fwrite(STDERR, "ОТКАЗ: харнесс покупает вызовы — только на wordtrainer_e2e_test, а не на «{$database}».\n");
    exit(1);
}

$base = dirname(__DIR__);
$calls = json_decode((string) file_get_contents("{$base}/den/model-calls.json"), true, flags: JSON_THROW_ON_ERROR);
$prompts = new PlanPromptFiles;
$system = $prompts->conversationSystem();
$prompt = new RenderedPrompt($system, $prompts->conversationVersion(), PromptShape::Full, hash('sha256', $system));
$port = app(ContentModelCatalog::class)->get(
    ProviderId::OpenAi, (string) config('plan.conversation.model'), ContentModelPlanBuilder::PURPOSE,
    (int) config('plan.conversation.timeout'), ContentModelPlanBuilder::CONVERSATION_ATTEMPTS, ContentModelPlanBuilder::JOURNAL_CONVERSATION,
);
if ($port === null) {
    fwrite(STDERR, "нет ключа OpenAI\n");
    exit(1);
}

const V1_HEADER = 'the lines the learner is preparing):';
const V2_HEADER = "the visit as prepared, exchange by exchange — LEARNER lines are the learner's to say, never yours; YOU lines show what you say there):";

/** @var array<string, list<array{kind: string, partner: string}>> scene id → its exchanges with both lines, in order */
$exchanges = [];
foreach (json_decode((string) file_get_contents("{$base}/den/scenes.json"), true, flags: JSON_THROW_ON_ERROR) as $scene) {
    foreach ($scene['dialogue'] as $exchange) {
        // The learner's message is the one written on a frame (it carries `phrase_id`, null only on a rescue line).
        $learner = null;
        $partner = null;
        foreach ($exchange['messages'] as $message) {
            if (array_key_exists('phrase_id', $message)) {
                $learner = $message;
            } else {
                $partner = $message;
            }
        }
        if ($exchange['kind'] !== 'rescue' && $learner !== null && $partner !== null) {
            $exchanges[$scene['scene_id']][] = ['kind' => $exchange['kind'], 'partner' => (string) $partner['text_target']];
        }
    }
}

/** The v1 CHECKPOINTS block written the way v2 writes it: both sides of every exchange of the prepared visit. */
function checkpointsV2(string $user, array $exchanges): string
{
    $out = [];
    $scene = null;
    $i = 0;
    foreach (explode("\n", str_replace(V1_HEADER, V2_HEADER, $user)) as $row) {
        if (preg_match('/^- (\S+) · /u', $row, $m) === 1 && isset($exchanges[$m[1]])) {
            $scene = $m[1];
            $i = 0;
        }
        if ($scene !== null && str_starts_with($row, '    · ')) {
            $learner = substr($row, strlen('    · '));
            $exchange = $exchanges[$scene][$i++] ?? null;
            $row = match (true) {
                $exchange === null => '    · LEARNER says: '.$learner,
                $exchange['kind'] === 'ask' => '    · LEARNER asks: '.$learner.' → YOU answer: '.$exchange['partner'],
                default => '    · YOU: '.$exchange['partner'].' → LEARNER answers: '.$learner,
            };
        }
        $out[] = $row;
    }

    return implode("\n", $out);
}

/** @return array{lines: list<string>, phrases: list<string>, heard: string, turn: string, said: list<string>} */
function readMessage(string $user): array
{
    $lines = [];
    $phrases = [];
    $heard = '';
    $turn = '';
    $said = [];
    foreach (explode("\n", $user) as $row) {
        if (str_starts_with($row, 'learner: ')) {
            $said[] = substr($row, strlen('learner: '));
        } elseif (str_starts_with($row, '    · ')) {
            $lines[] = explode(' = ', substr($row, strlen('    · ')), 2)[0];
        } elseif (preg_match('/^- (\S+:p\d+) · /u', $row, $m) === 1) {
            $phrases[] = $m[1];
        } elseif (str_starts_with($row, 'TURN: ')) {
            $turn = substr($row, 6);
        } elseif (str_starts_with($row, 'HEARD (')) {
            $heard = trim((string) preg_replace('/^HEARD \([^)]*\): ?/u', '', $row));
        }
    }

    if ($turn === 'said' && $heard !== '') {
        $said[] = $heard;
    }

    return ['lines' => $lines, 'phrases' => $phrases, 'heard' => $heard, 'turn' => $turn, 'said' => $said];
}

$out = [];
$spent = 0.0;
$flippedBefore = 0;
$flippedAfter = 0;
$flippedFirst = 0;
$cuts = 0;
$redos = 0;
$only = array_filter(array_map('intval', explode(',', (string) (getopt('', ['only::'])['only'] ?? ''))));
foreach ($calls as $n => $call) {
    if ($only !== [] && ! in_array($n + 1, $only, true)) {
        continue;
    }
    $read = readMessage($call['user']);
    $user = checkpointsV2($call['user'], $exchanges);
    $schema = PlanSchemas::conversationAgent($read['phrases']);
    $echoOf = $read['said'];

    $before = (string) ($call['answer']['reply_target'] ?? '');
    $beforeLine = RoleLines::learnerLineIn($before, $read['lines'], $echoOf);

    $answer = $port->complete($prompt, $user, $schema);
    $spent += (float) ($answer->costUsd ?? 0);
    $after = (string) ($answer->payload['reply_target'] ?? '');
    $afterLine = RoleLines::learnerLineIn($after, $read['lines'], $echoOf);
    $redo = null;
    if ($afterLine !== null) {
        $redos++;
        $again = $port->complete($prompt, $user."\nREDO: learner_line — do not say «{$afterLine}»: it is a LEARNER line, the learner says it, not you. Answer this move again as YOUR_ROLE", $schema);
        $spent += (float) ($again->costUsd ?? 0);
        $againTarget = (string) ($again->payload['reply_target'] ?? '');
        $redo = ['reply' => $againTarget, 'flip' => RoleLines::learnerLineIn($againTarget, $read['lines'], $echoOf), 'cut' => null];
        if ($redo['flip'] !== null) {
            // The last resort `ConversationMoves` takes: the sentence that says the learner line cut out, if the rest stands.
            $redo['cut'] = RoleLines::withoutLearnerLines($againTarget, (string) ($again->payload['reply_native'] ?? ''), $read['lines'], $echoOf);
            if ($redo['cut'] !== null) {
                $redo['flip'] = null;
            }
        }
    }
    $flippedBefore += $beforeLine !== null ? 1 : 0;
    $flippedFirst += $afterLine !== null ? 1 : 0;
    $cuts += ($redo['cut'] ?? null) !== null ? 1 : 0;
    $final = $redo === null ? $afterLine : $redo['flip'];
    $flippedAfter += $final !== null ? 1 : 0;

    $row = [
        'n' => $n + 1, 'call' => $call['id'], 'turn' => $read['turn'], 'heard' => $read['heard'],
        'before' => $before, 'before_flip' => $beforeLine,
        'after' => $after, 'after_flip' => $afterLine, 'after_end' => $answer->payload['end'] ?? null,
        'redo' => $redo, 'latency_ms' => $answer->latencyMs,
    ];
    $out[] = $row;
    printf("%2d %-6s HEARD «%s»\n   было:  %s%s\n   стало: %s%s\n", $n + 1, $read['turn'], $read['heard'], $before,
        $beforeLine === null ? '' : "   ← реплика ученика «{$beforeLine}»", $after, $afterLine === null ? '' : "   ← реплика ученика «{$afterLine}»");
    if ($redo !== null) {
        printf("   REDO:  %s%s\n", $redo['reply'], $redo['cut'] !== null ? "   → вырезано: «{$redo['cut']['target']}»" : ($redo['flip'] === null ? '' : "   ← всё ещё «{$redo['flip']}»"));
    }
}

printf("\nвызовов %d · переворотов: v1 %d · v2 первым ответом %d · после страховки %d (REDO %d, вырезано %d) · $%.6f\n", count($calls), $flippedBefore, $flippedFirst, $flippedAfter, $redos, $cuts, $spent);
if ($only !== []) {
    exit(0);
}
file_put_contents("{$base}/live/replay-den.json", json_encode(['calls' => $out, 'flipped_v1' => $flippedBefore, 'flipped_v2_first' => $flippedFirst, 'flipped_after_guard' => $flippedAfter, 'redo' => $redos, 'cut' => $cuts, 'usd' => round($spent, 6)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");
