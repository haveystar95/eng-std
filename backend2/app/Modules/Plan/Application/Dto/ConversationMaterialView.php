<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Plan\Domain\Service\ConversationTargets;
use App\Modules\Plan\Domain\ValueObject\ConversationCheckpoint;
use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;

/**
 * WHAT A TALK IS MADE OF: the scenes it walks, the constructions of each (its frames), and the four to seven of them it is
 * FOR — its targets (наряд CONV-2, п. 10; {@see ConversationTargets}). Read once when the talk starts and once per move —
 * the same material every time, so a talk is the same talk whether it is opened now or after a reconnect.
 *
 * THE ROLE KNOWS A TARGET BY A SHORT ID OF THE TALK (наряд FIX-4 §3): `T1`…`T7`, the targets' places in the talk's own
 * list — never `<scene>:<ref>`, whose refs repeat in every scene and whose scene ids share their first ten letters (the
 * owner's rehearsal opened the reception's p3 meaning the trainer's). The map from the short id to the target is the
 * server's: this list, the same every time.
 */
final readonly class ConversationMaterialView
{
    /** The short id of a target in the talk: its place in the talk's targets. */
    public const TARGET_ID_PREFIX = 'T';

    /** @var list<ConversationPhrase> «Скажи в разговоре»: what the entry lists, the strip ticks off and the summary counts */
    public array $targets;

    /**
     * @param  list<ConversationCheckpoint>  $checkpoints
     * @param  list<ConversationPhrase>  $phrases  every construction of the talk's scenes, targets and not
     * @param  string|null  $titleNative  «Поговори с врачом» — the entry title, by the role the talk opens with (кадр 37-5)
     * @param  string  $targetLang  the language the talk is held in — what the learner's words are read by
     */
    public function __construct(public array $checkpoints, public array $phrases, public ?string $titleNative = null, public string $targetLang = 'en')
    {
        $this->targets = ConversationTargets::of($checkpoints, $phrases);
    }

    /**
     * Every line the learner came to say in the talk, over all its scenes — what the role must never say as its own
     * ({@see \App\Modules\Plan\Domain\Service\RoleLines}, наряд CONV-2, п. 1).
     *
     * @return list<string>
     */
    public function learnerLines(): array
    {
        $out = [];
        foreach ($this->checkpoints as $checkpoint) {
            foreach ($checkpoint->keyLines as $line) {
                $out[] = $line['target'];
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * The scene the talk is on. A null id means every checkpoint is walked — then it is the LAST one,
     * not the first: a talk that has just ended stands in the scene it ended in, and snapping the
     * strip back to «Запись к врачу» after the doctor said goodbye is a lie the live run caught.
     */
    public function checkpoint(?string $sceneId): ?ConversationCheckpoint
    {
        if ($sceneId === null) {
            return $this->checkpoints === [] ? null : $this->checkpoints[count($this->checkpoints) - 1];
        }
        foreach ($this->checkpoints as $checkpoint) {
            if ($checkpoint->sceneId === $sceneId) {
                return $checkpoint;
            }
        }

        return $this->checkpoints[0] ?? null;
    }

    /** The scene after this one in the talk, or null for the last. */
    public function sceneAfter(string $sceneId): ?ConversationCheckpoint
    {
        foreach ($this->checkpoints as $i => $checkpoint) {
            if ($checkpoint->sceneId === $sceneId) {
                return $this->checkpoints[$i + 1] ?? null;
            }
        }

        return null;
    }

    /** Does the talk walk several scenes — the rehearsal, a review day — whose borders the server keeps (наряд FIX-4 §4)? */
    public function walksScenes(): bool
    {
        return count($this->checkpoints) > 1;
    }

    /** @return list<string> the scene ids, in the order the talk walks them */
    public function sceneIds(): array
    {
        return array_map(static fn (ConversationCheckpoint $c): string => $c->sceneId, $this->checkpoints);
    }

    /** The phrase of the talk by its id (`scene:ref`), or null. */
    public function phrase(string $id): ?ConversationPhrase
    {
        foreach ($this->phrases as $phrase) {
            if ($phrase->id() === $id) {
                return $phrase;
            }
        }

        return null;
    }

    /** @return list<ConversationPhrase> the constructions of one scene — its targets and its other frames, what a move there is judged against */
    public function phrasesOf(string $sceneId): array
    {
        return array_values(array_filter($this->phrases, static fn (ConversationPhrase $p): bool => $p->sceneId === $sceneId));
    }

    /** @return list<ConversationPhrase> the targets of one scene, in the talk's order */
    public function targetsOf(string $sceneId): array
    {
        return array_values(array_filter($this->targets, static fn (ConversationPhrase $p): bool => $p->sceneId === $sceneId));
    }

    /** Is this construction one of the talk's targets? */
    public function isTarget(string $id): bool
    {
        foreach ($this->targets as $target) {
            if ($target->id() === $id) {
                return true;
            }
        }

        return false;
    }

    /** The short id the role knows a target by (`T3`), or null for a construction that is no target. */
    public function shortId(ConversationPhrase $phrase): ?string
    {
        foreach ($this->targets as $i => $target) {
            if ($target->id() === $phrase->id()) {
                return self::TARGET_ID_PREFIX.($i + 1);
            }
        }

        return null;
    }

    /** The target a short id names (`T3`), or null for an id the talk does not have. */
    public function byShortId(string $shortId): ?ConversationPhrase
    {
        if (preg_match('/^'.self::TARGET_ID_PREFIX.'([1-9][0-9]*)$/', $shortId, $m) !== 1) {
            return null;
        }

        return $this->targets[(int) $m[1] - 1] ?? null;
    }
}
