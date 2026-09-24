<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Inspection;

use App\Modules\Plan\Application\Dto\Inspection\InspectedAudio;
use App\Modules\Plan\Application\Dto\Inspection\JournalSpeechCall;
use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Plan\Application\Port\PlanCallJournal;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Plan\Domain\Service\SpokenLines;
use App\Modules\Plan\Domain\ValueObject\Speaker;
use App\Modules\Plan\Domain\ValueObject\VoiceCast;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * WHAT A SCENE SAYS OUT LOUD, IN WHOSE VOICE, AND WHAT WAS BOUGHT FOR IT (наряд ADM-1, «Урок и голос»). The lines are the
 * ones the server voices ({@see SpokenLines}); the voice of each is the one its cast gives it ({@see VoiceCast}: the
 * partner — the role's gender, the learner — the profile's) and the key is the pack's, from config
 * ({@see LineSpeaker::voiceKeyFor()}) — never a voice id typed here. A stored file is `voiced` only in that voice; a line
 * with no such file is read by the phone. The text the vendor was sent is the request log's, matched to the file by its
 * voice, its size in bytes and the minute it was bought — when exactly one outbound call answers, else unknown.
 */
final readonly class VoiceTable
{
    /** How far apart the purchase row and its request-log row may be written. */
    private const MATCH_SECONDS = 120;

    public function __construct(
        private LineSpeaker $speaker,
        private PlanCallJournal $journal,
        private LanguagePacks $packs,
    ) {}

    /** @return list<VoiceLine> */
    public function of(PlanInspectionData $data, PlanScene $scene): array
    {
        $lesson = $scene->lesson();
        if ($lesson === null) {
            return [];
        }
        $lang = $data->plan->targetLang()->value;
        $cast = VoiceCast::of($scene->partnerVoiceGender(), $data->profileGender);
        $identities = $this->identities($lang);
        $audios = $data->audiosOfScene($scene->id()->value);
        $texts = $this->voicedTexts($audios);

        $wanted = [];
        foreach (SpokenLines::dialogue($lesson) as $line) {
            $wanted[] = [$line['ref'], $line['speaker'] === Speaker::Partner ? VoiceLine::PARTNER_LINE : VoiceLine::LEARNER_LINE, $line['speaker'], $line['text']];
        }
        $terms = $data->termsOf($scene->id()->value);
        foreach (SpokenLines::terms($terms, true) as $phrase) {
            $wanted[] = [$phrase['ref'], VoiceLine::PHRASE, Speaker::Learner, $phrase['text']];
        }
        $ends = $this->packs->for($lang)->sentenceEnds();
        foreach ($terms as $term) {
            foreach (SpokenLines::fillers($term, $ends) as $filler) {
                if ($filler['voicedAs'] === $filler['ref']) {
                    $wanted[] = [$filler['ref'], VoiceLine::FILLER, Speaker::Learner, $filler['text']];
                }
            }
        }
        foreach (SpokenLines::terms($terms, false) as $word) {
            $wanted[] = [$word['ref'], VoiceLine::WORD, Speaker::Learner, $word['text']];
        }

        $out = [];
        foreach ($wanted as [$ref, $kind, $speaker, $text]) {
            $gender = $cast->genderOf($speaker);
            $expected = $this->speaker->voiceKeyFor($lang, $speaker, $gender);
            $stored = array_values(array_filter($audios, static fn (InspectedAudio $a): bool => $a->lineRef === $ref));
            $own = null;
            $others = [];
            foreach ($stored as $audio) {
                if ($audio->voiceKey === $expected) {
                    $own = $audio;
                } else {
                    $others[] = $audio;
                }
            }
            $out[] = new VoiceLine(
                ref: $ref,
                kind: $kind,
                speaker: $speaker->value,
                text: $text,
                gender: $gender->value,
                genderRule: $this->rule($speaker, $scene, $data),
                expectedVoice: $expected,
                audio: $own,
                others: $others,
                identities: $identities,
                voicedText: $own === null ? null : ($texts[$own->id] ?? null),
            );
        }

        return $out;
    }

    /**
     * Who a voice key is, by the pack's config: `role:gender` for each of the four voices of the language.
     *
     * @return array<string, string>
     */
    public function identities(string $lang): array
    {
        $out = [];
        foreach ([Speaker::Partner, Speaker::Learner] as $speaker) {
            foreach ([VoiceGender::Female, VoiceGender::Male] as $gender) {
                $key = $this->speaker->voiceKeyFor($lang, $speaker, $gender);
                if ($key !== null) {
                    $out[$key] = $speaker->value.':'.$gender->value;
                }
            }
        }

        return $out;
    }

    /** Why the voice is this gender — the rule of the cast, as it applied to this scene and this learner. */
    private function rule(Speaker $speaker, PlanScene $scene, PlanInspectionData $data): string
    {
        if ($speaker === Speaker::Learner) {
            return $data->profileGender === null
                ? 'ученик: пол в профиле не указан → по умолчанию '.VoiceCast::DEFAULT_LEARNER->value
                : 'ученик: пол профиля — '.$data->profileGender->value;
        }
        $said = $scene->answer()?->roleGender;

        return $said === null
            ? 'собеседник: role_gender урока пуст → '.($scene->partnerVoiceGender() ?? PlanScene::DEFAULT_PARTNER_VOICE)->value.' (по умолчанию)'
            : 'собеседник: role_gender урока — '.$said->value;
    }

    /**
     * The text the vendor was sent for each stored file, by audio id — a request-log row of the same voice, returning the
     * same number of bytes, logged within two minutes of the purchase; exactly one such row, else nothing.
     *
     * @param  list<InspectedAudio>  $audios
     * @return array<string, string>
     */
    private function voicedTexts(array $audios): array
    {
        $dated = array_values(array_filter($audios, static fn (InspectedAudio $a): bool => $a->createdAt !== null));
        if ($dated === []) {
            return [];
        }
        $times = array_map(static fn (InspectedAudio $a): int => (int) $a->createdAt?->getTimestamp(), $dated);
        $calls = $this->journal->speechCalls(
            (new \DateTimeImmutable('@'.(min($times) - self::MATCH_SECONDS))),
            (new \DateTimeImmutable('@'.(max($times) + self::MATCH_SECONDS))),
        );

        $out = [];
        foreach ($dated as $audio) {
            $voiceId = explode(':', $audio->voiceKey)[2] ?? '';
            $at = (int) $audio->createdAt?->getTimestamp();
            $hits = array_values(array_filter($calls, static fn (JournalSpeechCall $c): bool => $c->voiceId === $voiceId
                && $c->audioBytes === $audio->bytes && $c->text !== null && abs($c->occurredAt->getTimestamp() - $at) <= self::MATCH_SECONDS));
            if (count($hits) === 1 && $hits[0]->text !== null) {
                $out[$audio->id] = $hits[0]->text;
            }
        }

        return $out;
    }
}
