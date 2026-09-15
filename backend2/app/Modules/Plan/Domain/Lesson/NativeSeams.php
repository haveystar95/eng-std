<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

use App\Modules\Plan\Domain\Service\FrameText;

/**
 * THE NATIVE SENTENCES A LESSON PUTS TOGETHER (наряд GEN-2b): every frame with a slot, in the learner's language,
 * said with each of its fillers — «Вот ___» with «мой паспорт». The server builds them the way it builds the
 * target lines ({@see FrameText::fill()}); whether each one reads as the learner's language is no code's to say —
 * the seam judge reads them all in one call a day.
 */
final class NativeSeams
{
    /**
     * @return list<array{id: string, pattern: string, value: string, sentence: string}> `id` is the filler's
     *                                                                                  address (`p3.f2`)
     */
    public static function of(Lesson $answer): array
    {
        $out = [];
        foreach ($answer->phrases as $phrase) {
            if ($phrase->slot === null || ! FrameText::hasSlot($phrase->frameNative)) {
                continue;
            }
            foreach ($phrase->slot->fillers as $index => $filler) {
                if (trim($filler->native) === '') {
                    continue;
                }
                $out[] = [
                    'id' => $phrase->id.'.f'.($index + 1),
                    'pattern' => $phrase->frameNative,
                    'value' => $filler->native,
                    'sentence' => FrameText::fill($phrase->frameNative, $filler->native),
                ];
            }
        }

        return $out;
    }
}
