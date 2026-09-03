<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * WHAT THE LISTENING STEP LEARNED — three lines, three self-taps, and one decision.
 *
 * The entry's optional step (кадры V4·03…03г) plays three lines the other person would say and asks
 * «как ощущается» after each: «Понял» or «Не совсем». There is no right answer and nothing is
 * scored — the canon is explicit that the screen shows «никаких баллов, процентов и „твой уровень“».
 * What the taps buy is ONE decision about the plan, and it is stated to the learner in the same
 * sentence it is made:
 *
 *     all three understood  → «Понимаешь на слух уверенно: сделаю упор на говорение»
 *     anything else         → «Понял: сделаю упор на понимание на слух»
 *
 * The «anything else» is deliberately the wide side, mixed answers included. Two out of three is
 * not a pass: the learner said, about a line they will actually hear, that they did not quite get
 * it, and a plan that answers that with more speaking drills is a plan arguing with its owner.
 *
 * ## Why the lines are kept and not just the verdict
 *
 * `{{diagnostics}}` of P1 is prose the model reads to shape the scenes, and «пользователь не понял
 * две реплики из трёх» tells it far less than the two lines themselves. The verdict alone would
 * also make the record unauditable: a plan whose balance looks wrong could not be traced back to
 * what the learner actually heard.
 *
 * Immutable and re-derived on read: {@see emphasis()} is a function of the taps, never a stored
 * field, so a record cannot carry a verdict that disagrees with its own rows.
 */
final readonly class ListeningDiagnostics
{
    /** «Понял: сделаю упор на понимание на слух» — the wide side, mixed answers included. */
    public const EMPHASIS_UNDERSTANDING = 'understanding';

    /** «Понимаешь на слух уверенно: сделаю упор на говорение» — only when every line was understood. */
    public const EMPHASIS_SPEAKING = 'speaking';

    /**
     * @param  list<array{text: string, translation: string, place: string, understood: bool}>  $lines
     *         never empty — a record with no lines is a step that did not happen, and that is
     *         expressed by having no record at all ({@see fromLines()} returns null)
     */
    private function __construct(public array $lines) {}

    /**
     * @param  list<array{text: string, translation: string, place: string, understood: bool}>  $lines
     * @return self|null NULL when nothing was heard — «шаг пропущен», which is not a diagnostics
     *         of «всё непонятно» and must never become one
     */
    public static function fromLines(array $lines): ?self
    {
        $clean = [];
        foreach ($lines as $line) {
            $text = trim($line['text']);
            if ($text === '') {
                continue;
            }

            $clean[] = [
                'text' => $text,
                'translation' => trim($line['translation']),
                'place' => trim($line['place']),
                'understood' => $line['understood'],
            ];
        }

        return $clean === [] ? null : new self($clean);
    }

    /** @param array<array-key, mixed>|null $raw as it sits in `learning_plans.listening_diagnostics` */
    public static function fromArray(?array $raw): ?self
    {
        $rows = $raw['lines'] ?? null;
        if (! is_array($rows)) {
            return null;
        }

        $lines = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ! is_string($row['text'] ?? null)) {
                continue;
            }

            $lines[] = [
                'text' => $row['text'],
                'translation' => is_string($row['translation'] ?? null) ? $row['translation'] : '',
                'place' => is_string($row['place'] ?? null) ? $row['place'] : '',
                'understood' => (bool) ($row['understood'] ?? false),
            ];
        }

        return self::fromLines($lines);
    }

    /** {@see EMPHASIS_SPEAKING} only when every line was understood. */
    public function emphasis(): string
    {
        foreach ($this->lines as $line) {
            if ($line['understood'] === false) {
                return self::EMPHASIS_UNDERSTANDING;
            }
        }

        return self::EMPHASIS_SPEAKING;
    }

    /** @return array{lines: list<array{text: string, translation: string, place: string, understood: bool}>} */
    public function toArray(): array
    {
        return ['lines' => $this->lines];
    }
}
