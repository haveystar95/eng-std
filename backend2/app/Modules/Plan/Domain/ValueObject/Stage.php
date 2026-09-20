<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * THE STAGES A DAY IS WALKED IN, in their order (наряд CONV-1).
 *
 * A scene day walks six of them: words → phrases → dialogue → listen → speak → CONVERSATION. The
 * sixth is the live talk with the agent and it is the only stage made of no cards at all — its
 * journal is the conversation itself ({@see \App\Modules\Plan\Domain\Entity\Conversation}), so
 * «пройден» for it is «разговор окончен» and nothing is recounted.
 *
 * `recall` belongs to the rehearsal day only («Вспомнить», кадры 37-1, 37-3): the plan's own lines
 * read through once and said aloud. It stands here after `speak` because no day has both — the
 * order inside a day is what this list is for, and the rehearsal's two stages come out `recall`,
 * `conversation`.
 */
enum Stage: string
{
    case Words = 'words';
    case Phrases = 'phrases';
    case Dialogue = 'dialogue';
    case Listen = 'listen';
    case Speak = 'speak';
    case Recall = 'recall';
    case Conversation = 'conversation';

    /** @return list<self> */
    public static function ordered(): array
    {
        return [self::Words, self::Phrases, self::Dialogue, self::Listen, self::Speak, self::Recall, self::Conversation];
    }

    /**
     * The stages made of cards — every stage but the conversation, which is its own journal.
     *
     * @return list<self>
     */
    public static function ofCards(): array
    {
        return array_values(array_filter(self::ordered(), static fn (self $stage): bool => $stage !== self::Conversation));
    }

    public function position(): int
    {
        return (int) array_search($this, self::ordered(), true);
    }
}
