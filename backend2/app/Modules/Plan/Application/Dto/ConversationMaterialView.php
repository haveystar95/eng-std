<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Plan\Domain\ValueObject\ConversationCheckpoint;
use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;

/**
 * WHAT A TALK IS MADE OF: the scenes it walks and the phrases it listens for. Read once when the
 * talk starts and once per move — the same material every time, so a talk is the same talk whether
 * it is opened now or after a reconnect.
 */
final readonly class ConversationMaterialView
{
    /**
     * @param  list<ConversationCheckpoint>  $checkpoints
     * @param  list<ConversationPhrase>  $phrases
     */
    public function __construct(public array $checkpoints, public array $phrases) {}

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

    /** @return list<string> the scene ids, in the order the talk walks them */
    public function sceneIds(): array
    {
        return array_map(static fn (ConversationCheckpoint $c): string => $c->sceneId, $this->checkpoints);
    }

    /** @return list<ConversationPhrase> the phrases of one scene */
    public function phrasesOf(string $sceneId): array
    {
        return array_values(array_filter($this->phrases, static fn (ConversationPhrase $p): bool => $p->sceneId === $sceneId));
    }
}
