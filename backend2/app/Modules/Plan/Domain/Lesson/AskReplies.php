<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

/**
 * THE PARTNER'S REPLIES TO THE LEARNER'S QUESTIONS, AS THE SEAM JUDGE READS THEM (наряд GEN-4c, `lesson_seam_judge.v1.3`):
 * every statement of the partner paired with an `ask` frame that has fillers ({@see Skeleton::repliesToAsks()}) — the
 * question with its slot, the values it is asked with, the reply said to every one of them alike. Whether the reply names a
 * value by its meaning is no code's to say («lucru cu clienții și pregătirea documentelor» to «Postul include ___?» with
 * «lucrul cu clienții», «pregătirea actelor»): the judge reads them in the call it reads the native seams in.
 */
final class AskReplies
{
    /**
     * @param  list<string>|null  $lines  only the replies of these partner lines; null — every one
     * @return list<array{id: string, question: string, values: list<string>, reply: string}> `id` is the partner line's (`a6`), one item a line
     */
    public static function of(Skeleton $skeleton, ?array $lines = null): array
    {
        $out = [];
        foreach ($skeleton->repliesToAsks() as [$line, $frame]) {
            $values = array_values(array_filter(
                array_map(static fn (Filler $f): string => trim($f->target), $frame->phrase->fillers()),
                static fn (string $value): bool => $value !== '',
            ));
            if ($values === [] || isset($out[$line->id]) || ($lines !== null && ! in_array($line->id, $lines, true))) {
                continue;
            }
            $out[$line->id] = ['id' => $line->id, 'question' => $frame->phrase->frameTarget, 'values' => $values, 'reply' => $line->textTarget];
        }

        return array_values($out);
    }
}
