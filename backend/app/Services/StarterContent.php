<?php

namespace App\Services;

use App\Models\User;

/** Seeds a small starter collection for brand-new users. */
class StarterContent
{
    private const WORDS = [
        ['term' => 'to afford', 'translation' => 'позволить себе', 'transcription' => 'əˈfɔːd', 'example' => "I can't afford a new car right now.", 'cefr_level' => 'B1'],
        ['term' => 'to improve', 'translation' => 'улучшать(ся)', 'transcription' => 'ɪmˈpruːv', 'example' => 'She wants to improve her English.', 'cefr_level' => 'A2'],
        ['term' => 'to borrow', 'translation' => 'брать взаймы', 'transcription' => 'ˈbɒrəʊ', 'example' => 'Can I borrow your pen?', 'cefr_level' => 'A2'],
        ['term' => 'to remind', 'translation' => 'напоминать', 'transcription' => 'rɪˈmaɪnd', 'example' => 'Remind me to call him tomorrow.', 'cefr_level' => 'B1'],
        ['term' => 'on purpose', 'translation' => 'нарочно, специально', 'transcription' => 'ɒn ˈpɜːpəs', 'example' => 'He did it on purpose.', 'cefr_level' => 'B1'],
        ['term' => 'in advance', 'translation' => 'заранее', 'transcription' => 'ɪn ədˈvɑːns', 'example' => 'Please book your seat in advance.', 'cefr_level' => 'B1'],
    ];

    public function __construct(private readonly Vocabulary $vocab) {}

    public function seed(User $user): void
    {
        $collection = $user->collections()->create([
            'title' => 'Everyday Essentials',
            'emoji' => '📚',
            'topic' => 'general',
            'source' => 'manual',
        ]);

        foreach (self::WORDS as $w) {
            $this->vocab->addToCollection($user, $collection, $w);
        }
    }
}
