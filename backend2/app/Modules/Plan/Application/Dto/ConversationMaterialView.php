<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Plan\Domain\Service\ConversationTargets;
use App\Modules\Plan\Domain\ValueObject\ConversationCheckpoint;
use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;

/**
 * WHAT A TALK IS MADE OF: the scenes it walks, the phrases it listens for, and the four to seven of them it is FOR —
 * its targets (наряд CONV-2, п. 10; {@see ConversationTargets}). Read once when the talk starts and once per move — the
 * same material every time, so a talk is the same talk whether it is opened now or after a reconnect.
 */
final readonly class ConversationMaterialView
{
    /** @var list<ConversationPhrase> «Скажи в разговоре»: what the entry lists, the strip ticks off and the summary counts */
    public array $targets;

    /**
     * @param  list<ConversationCheckpoint>  $checkpoints
     * @param  list<ConversationPhrase>  $phrases
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

    /** @return list<ConversationPhrase> the phrases of one scene */
    public function phrasesOf(string $sceneId): array
    {
        return array_values(array_filter($this->phrases, static fn (ConversationPhrase $p): bool => $p->sceneId === $sceneId));
    }
}
