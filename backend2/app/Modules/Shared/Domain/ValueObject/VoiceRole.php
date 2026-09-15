<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\ValueObject;

/**
 * WHO IN A SCENE A VOICE BELONGS TO (TTS-2): the partner or the learner.
 *
 * The pack picks a voice by role AND gender, because a man or a woman can be either: the learner always sounds like the
 * same person, and a partner of the same gender must not sound like them. So the pack has a voice of each gender for
 * each role — the roles' voices in a scene are always different, whatever gender each role has.
 */
enum VoiceRole: string
{
    case Partner = 'partner';
    case Learner = 'learner';
}
