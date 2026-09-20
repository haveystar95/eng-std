<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;
use App\Modules\Shared\Domain\Service\SpeechMatch;
use App\Modules\Shared\Domain\ValueObject\SpeechMode;
use App\Modules\Shared\Domain\ValueObject\SpeechPack;

/**
 * WHICH PHRASES OF THE PLAN THE LEARNER ACTUALLY SAID (наряд CONV-1) — by the product's one rule of
 * spoken grading ({@see SpeechMatch}) in its `free` mode, and by nothing else.
 *
 * THE MODEL IS NOT ASKED THIS. The agent's schema carries a `phrases_used` of its own and the
 * server stores what it answered, but the number the summary prints is the CODE's: only the server
 * grades, the rule is one for the whole product, and a count that depends on a model's mood is a
 * count the learner cannot trust twice. The model's opinion is kept for the report to compare
 * against, never for the score.
 *
 * `free` is the right mode here for the same reason it is right in «Говорю сам»: the learner is
 * saying their own sentence, so what is listened for is the frame's key, not the lesson's filler.
 */
final readonly class SpokenPhrases
{
    public function __construct(private SpeechMatch $speech = new SpeechMatch) {}

    /**
     * @param  list<ConversationPhrase>  $phrases
     * @return list<string> the ids ({@see ConversationPhrase::id()}) of the phrases heard in this line
     */
    public function heardIn(string $heard, array $phrases, SpeechPack $pack): array
    {
        if (trim($heard) === '') {
            return [];
        }
        $out = [];
        foreach ($phrases as $phrase) {
            if (trim($phrase->key) !== '' && $this->speech->said($heard, $phrase->key, SpeechMode::Free, $pack)) {
                $out[] = $phrase->id();
            }
        }

        return array_values(array_unique($out));
    }
}
