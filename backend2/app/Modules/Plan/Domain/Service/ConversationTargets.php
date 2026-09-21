<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\ValueObject\ConversationCheckpoint;
use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;

/**
 * «СКАЖИ В РАЗГОВОРЕ» — THE PHRASES A TALK IS FOR (наряд CONV-2, п. 10): four to seven of the plan's phrases, taken over
 * the talk's checkpoints in their order, that the entry card lists (кадр 37-5), the strip of the ribbon ticks off turn by
 * turn, and the summary counts (кадр 37-12). One list for all three, so «3 из 5» on the summary is the same five the
 * learner was shown before the talk began.
 *
 * Inside a scene the phrases come in the order the learner SAYS them in its visit — the frames of its key lines, first
 * appearance first — and a frame no line of the visit stands on comes after them. Over several scenes (the rehearsal,
 * a review day) the {@see MAX} places are shared out one by one, scene after scene, so a talk over three scenes asks for
 * phrases of all three and not seven of the first; what a scene has fewer of, the others take. A talk over one scene of
 * seven frames asks for all seven; a scene with fewer phrases asks for what it has.
 *
 * Pure and deterministic: the same material is the same list every time the talk is read.
 */
final class ConversationTargets
{
    /** The most phrases a talk asks for — what the entry card and the strip have room for. */
    public const MAX = 7;

    /**
     * @param  list<ConversationCheckpoint>  $checkpoints  in the order the talk walks them
     * @param  list<ConversationPhrase>  $phrases  every phrase of their scenes
     * @return list<ConversationPhrase>
     */
    public static function of(array $checkpoints, array $phrases): array
    {
        $queues = [];
        foreach ($checkpoints as $checkpoint) {
            $byRef = [];
            foreach ($phrases as $phrase) {
                if ($phrase->sceneId === $checkpoint->sceneId) {
                    $byRef[$phrase->ref] = $phrase;
                }
            }
            $queue = [];
            foreach ($checkpoint->keyLines as $line) {
                $ref = $line['phrase_ref'];
                if ($ref !== null && isset($byRef[$ref]) && ! isset($queue[$ref])) {
                    $queue[$ref] = $byRef[$ref];
                }
            }
            foreach ($byRef as $ref => $phrase) {
                $queue[$ref] ??= $phrase;
            }
            $queues[] = array_values($queue);
        }

        $taken = array_fill(0, count($queues), 0);
        $total = 0;
        do {
            $grew = false;
            foreach ($queues as $i => $queue) {
                if ($total < self::MAX && $taken[$i] < count($queue)) {
                    $taken[$i]++;
                    $total++;
                    $grew = true;
                }
            }
        } while ($grew && $total < self::MAX);

        $out = [];
        foreach ($queues as $i => $queue) {
            foreach (array_slice($queue, 0, $taken[$i]) as $phrase) {
                $out[] = $phrase;
            }
        }

        return $out;
    }
}
