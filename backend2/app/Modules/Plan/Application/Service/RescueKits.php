<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\LineToSay;
use App\Modules\Plan\Application\Dto\SpokenAudio;
use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Plan\Application\Port\RescueAudioStore;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\ValueObject\Speaker;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * THE RESCUE KIT OF A PLAN — BY ITS PAIR (наряд LANG-1b §2). The kit is the TARGET's (its pack's `rescue`: six lines a
 * learner says when stuck — «Sorry?», «Could you say that more slowly, please?», «I don't understand.», «One moment.», «Can
 * you write it down?», «Thank you.»), each shown with its translation into the LEARNER's language: a Russian learner of
 * German gets «Wie bitte?» over «Простите?», a Belarusian learner of English «Sorry?» over «Прабачце?». Before the наряд
 * one list, English with Russian, went to every pair.
 *
 * THE KIT IS SAID IN THE LEARNER'S VOICE of the target (the gender their profile says, male while it says none — the voice
 * the learner's own lines are read in), bought by a job of its own when a lesson of the plan is accepted — the first day's,
 * right after the plan is built ({@see \App\Modules\Plan\Application\Command\VoiceRescueKitHandler}) — and FILED BY
 * (target, gender, voice, line) ({@see audioKey()}): every later plan of that target and gender reads the same file,
 * nothing is bought twice. A line not bought yet — a voice switched off, a job that has not run — goes out without its
 * sound; the phone reads it itself.
 */
final readonly class RescueKits
{
    /** The refs the kit's lines are asked for by, in a scene's voice job: `rescue:1` … `rescue:6`. */
    public const REF = 'rescue:';

    public function __construct(
        private LanguagePacks $packs,
        private LineSpeaker $speaker,
        private RescueAudioStore $store,
    ) {}

    /**
     * The kit of a plan of `$target` for a learner of `$native`: the target's lines in its pack's order, each with its
     * translation and the key of its sound in the learner's voice — null until it is bought. A line the pack does not
     * translate into the learner's language is left out.
     *
     * @return list<array{text_target: string, text_native: string, audio_key: string|null}>
     */
    public function of(string $target, string $native, VoiceGender $learner): array
    {
        $voice = $this->speaker->voiceKeyFor($target, Speaker::Learner, $learner);
        $out = [];
        foreach ($this->packs->for($target)->rescue() as $row) {
            $translation = $row['native'][strtolower($native)] ?? null;
            if ($translation === null) {
                continue;
            }
            $key = $voice === null ? null : self::audioKey($target, $learner, $voice, $row['target']);
            $out[] = [
                'text_target' => $row['target'],
                'text_native' => $translation,
                'audio_key' => $key !== null && $this->store->has($key) ? $key : null,
            ];
        }

        return $out;
    }

    /**
     * The lines of `$target`'s kit the learner's voice has not said yet — what the kit's voice job of a plan of that target
     * buys. Nothing when the voice is off or the target has no kit.
     *
     * @return list<LineToSay>
     */
    public function owed(string $target, VoiceGender $learner): array
    {
        $voice = $this->speaker->voiceKeyFor($target, Speaker::Learner, $learner);
        if ($voice === null) {
            return [];
        }
        $out = [];
        foreach ($this->packs->for($target)->rescue() as $index => $row) {
            if (! $this->store->has(self::audioKey($target, $learner, $voice, $row['target']))) {
                $out[] = new LineToSay(self::REF.($index + 1), $row['target'], Speaker::Learner, $learner);
            }
        }

        return $out;
    }

    /** Files a line of the kit as bought, by the voice it was bought in. */
    public function keep(string $target, VoiceGender $learner, LineToSay $line, SpokenAudio $audio): void
    {
        $this->store->put(self::audioKey($target, $learner, $audio->voiceKey, $line->text), $audio);
    }

    /**
     * The key of a line of the kit: the target, the learner's gender, the voice and the line — so another voice (a pack's
     * voice changed) is another file, as with every line the plan buys (DECISIONS п. 248).
     */
    public static function audioKey(string $target, VoiceGender $learner, string $voiceKey, string $text): string
    {
        return sha1(implode("\n", [strtolower(trim($target)), $learner->value, $voiceKey, $text]));
    }
}
