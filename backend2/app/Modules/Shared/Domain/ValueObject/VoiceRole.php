<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\ValueObject;

/**
 * WHO IN A SCENE A VOICE BELONGS TO (TTS-2): the partner or the learner.
 *
 * The pack picks a voice by role AND gender, because a man can be either: the learner who is a man always sounds
 * like the same man, and a partner who is a man must not sound like him. So the pack has a man's voice for each
 * role, and one woman's voice serves both.
 */
enum VoiceRole: string
{
    case Partner = 'partner';
    case Learner = 'learner';
}
