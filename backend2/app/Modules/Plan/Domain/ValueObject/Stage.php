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
 * read through once and said aloud. `repetition` belongs to the review day only («Повторение», кадр
 * 37-2; наряд BACK-TAILS-2 §3): its own cards — the exchanges of the days it repeats, said aloud —
 * which used to stand under `speak` and read as «Говорю сам» on a day that has no such stage. Both
 * stand after `speak` because no day has two of them — the order inside a day is what this list is
 * for: the rehearsal comes out `recall`, `conversation`; the review `words`, `phrases`, `repetition`,
 * `conversation`, as far as it deals them.
 */
enum Stage: string
{
    case Words = 'words';
    case Phrases = 'phrases';
    case Dialogue = 'dialogue';
    case Listen = 'listen';
    case Speak = 'speak';
    case Recall = 'recall';
    case Repetition = 'repetition';
    case Conversation = 'conversation';

    /** @return list<self> */
    public static function ordered(): array
    {
        return [self::Words, self::Phrases, self::Dialogue, self::Listen, self::Speak, self::Recall, self::Repetition, self::Conversation];
    }

    /**
     * The stages a learner SAYS their own lines in — «Говорю сам» of a scene day, «Вспомнить» of the rehearsal and
     * «Повторение» of a review: what «Сказал сам N реплик» counts and what «Ещё раз» of a passed day walks again.
     *
     * @return list<self>
     */
    public static function spoken(): array
    {
        return [self::Speak, self::Recall, self::Repetition];
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
