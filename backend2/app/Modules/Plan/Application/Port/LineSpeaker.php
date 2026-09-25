<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Application\Dto\LineToSay;
use App\Modules\Plan\Application\Dto\SpokenAudio;
use App\Modules\Plan\Application\Dto\VoiceBalance;
use App\Modules\Plan\Domain\ValueObject\Speaker;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * Says what a scene says out loud with the language pack's voices (DAY-UI-3, TTS-2): every line — the partner's, the
 * learner's, a phrase, a phrase with a filler, a word — on a call of its own, in the voice its speaker has in the scene.
 *
 * Nothing handed over is «not now»: speech is switched off, the pack has no such voice, or the vendor refused the text —
 * the client reads those lines with the phone's voice until a later run buys them. A transient vendor error (the
 * concurrency limit, a 5xx, the network) and a refusal of the vendor account (no credits, a voice the plan does not
 * include) propagate: the first makes the job wait, the second fails it with the vendor's code.
 */
interface LineSpeaker
{
    /**
     * @param  list<LineToSay>  $lines
     * @param  callable(string, SpokenAudio): void  $keep  called with the ref as soon as a line is bought
     */
    public function sayEach(string $lang, array $lines, callable $keep): void;

    /**
     * What saying these lines would cost in the vendor's credits, before anything is bought — the estimate a credits cap
     * is checked against. 0 — speech is off or the pack has none of their voices.
     *
     * @param  list<LineToSay>  $lines
     */
    public function creditsFor(string $lang, array $lines): int;

    /**
     * The key a file of this voice is stored under, so a stored one is found before buying. Null — no such voice.
     * `$voice` — the partner's voice fixed for the scene (наряд FIX-4c §1); null — the gender's first.
     */
    public function voiceKeyFor(string $lang, Speaker $speaker, VoiceGender $gender, ?string $voice = null): ?string;

    /** What the vendor account has left, as the vendor counts it; null — speech is off or the vendor would not say. */
    public function balance(): ?VoiceBalance;
}
