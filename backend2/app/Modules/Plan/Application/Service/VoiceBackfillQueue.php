<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\LineToSay;
use App\Modules\Plan\Application\Dto\VoiceBatch;
use App\Modules\Plan\Application\Dto\VoicePacket;
use App\Modules\Plan\Application\Dto\VoicePacketLine;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * WHAT THE VOICE BACKFILL BUYS, AND IN WHICH ORDER (DAY-UI-3; owner 14.09: «уложиться в ~100 вызовов»).
 *
 * The vendor's free tier counts REQUESTS — a hundred a day — so existing scenes are not bought scene by scene (three
 * or four calls each) but by kind, in the owner's order: the dialogues of the scenes whose partner lines are missing,
 * then the dialogues of the scenes missing only the learner's lines, then phrases, then words. A dialogue is one call
 * per scene — the whole conversation with its two voices, only the missing lines kept. Phrases and words are packed
 * ACROSS scenes, up to {@see PER_PACKET} a call, one language and one voice a packet. What does not fit into the
 * vendor's day stays owed, and the next run starts where this one stopped — nothing here remembers a position.
 */
final readonly class VoiceBackfillQueue
{
    /** Phrases or words in one call: long enough to spend few requests, short enough to cut reliably. */
    public const PER_PACKET = 12;

    public function __construct(private SceneVoiceQueue $scenes) {}

    /**
     * @param  list<PlanSceneId>  $sceneIds  in the order scenes are served within a kind
     * @return list<VoicePacket>
     */
    public function packets(array $sceneIds): array
    {
        $partnerDialogues = [];
        $learnerDialogues = [];
        /** @var array<string, list<array{line: VoicePacketLine, cast: array<string, VoiceGender>}>> $phrases */
        $phrases = [];
        /** @var array<string, list<array{line: VoicePacketLine, cast: array<string, VoiceGender>}>> $words */
        $words = [];

        foreach ($sceneIds as $sceneId) {
            $debt = $this->scenes->owed($sceneId);
            if ($debt === null) {
                continue;
            }
            $cast = $debt->castIsNew ? [$sceneId->value => $debt->cast->partner] : [];
            foreach ($debt->batches as $batch) {
                $owed = array_flip($batch->owed);
                $lines = array_map(
                    static fn (LineToSay $l): VoicePacketLine => new VoicePacketLine($sceneId, $l, isset($owed[$l->ref])),
                    $batch->lines,
                );
                if ($batch->kind === VoiceBatch::DIALOGUE) {
                    $packet = new VoicePacket(VoicePacket::DIALOGUE, $debt->lang, $lines, $cast);
                    if ($debt->partnerLines > 0) {
                        $partnerDialogues[] = $packet;
                    } else {
                        $learnerDialogues[] = $packet;
                    }

                    continue;
                }
                foreach ($lines as $line) {
                    $group = $debt->lang.'|'.$line->line->voice->value;
                    if ($batch->kind === VoiceBatch::PHRASES) {
                        $phrases[$group][] = ['line' => $line, 'cast' => $cast];
                    } else {
                        $words[$group][] = ['line' => $line, 'cast' => $cast];
                    }
                }
            }
        }

        return [
            ...$partnerDialogues,
            ...$learnerDialogues,
            ...self::pack(VoicePacket::PHRASES, $phrases),
            ...self::pack(VoicePacket::WORDS, $words),
        ];
    }

    /**
     * @param  array<string, list<array{line: VoicePacketLine, cast: array<string, VoiceGender>}>>  $groups  «lang|voice» → lines
     * @return list<VoicePacket>
     */
    private static function pack(string $kind, array $groups): array
    {
        $out = [];
        foreach ($groups as $group => $entries) {
            $lang = explode('|', (string) $group, 2)[0];
            foreach (array_chunk($entries, self::PER_PACKET) as $chunk) {
                $casts = [];
                foreach ($chunk as $entry) {
                    $casts += $entry['cast'];
                }
                $out[] = new VoicePacket($kind, $lang, array_column($chunk, 'line'), $casts);
            }
        }

        return $out;
    }
}
