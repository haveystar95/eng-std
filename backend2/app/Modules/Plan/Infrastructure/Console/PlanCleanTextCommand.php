<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Console;

use App\Modules\Plan\Domain\Service\ModelText;
use App\Modules\Plan\Domain\Service\ReadingLetters;
use App\Modules\Shared\Domain\Service\TextNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * THE MODEL'S TEXT ALREADY STORED, WITHOUT THE CHARACTERS THAT PRINT NOTHING (наряд LANG-1b §6). Since the same наряд every
 * answer of the plan's model is read through {@see ModelText} before anything is kept; what was kept before is cleaned
 * here, by the same rule ({@see TextNormalizer::visible()}): the title of a live day, «Опы\u{0004}т и навыки», printed an
 * empty box on the phone.
 *
 * It reads the columns that hold the MODEL's text and nothing else: the plan (titles, the event's phrases, the learner's
 * role, the cover's image prompt, the plan call's findings), the scenes (titles, what they teach, goals, roles, the brief,
 * the image prompt, the lesson as the model wrote it, its findings), the words and phrases of a scene (texts, readings,
 * definitions, examples, keys, frames with their fillers), and the dealt cards (their payload is the lesson's text). The
 * photo vendor's author names (`image_author`: «Sed\u{200C} "Creatives" Sardar» — a Persian name keeps its non-joiner), the
 * learner's own goal, the talk's journal of turns (written once, never changed) and the append-only journal of rejections
 * are not the model's text as stored, and are left as they are.
 *
 * THE READINGS ALREADY STORED, IN THE LETTERS THEIR LEARNER READS (наряд LANG-1b, последнее): the readings of the words and
 * phrases of a scene (`plan_terms`: the reading, the frame's reading, the fillers' readings in the slot) and of the dealt
 * cards (every `pronunciation_native` and `frame_pronunciation_native` of a payload) are read, after the characters that
 * print nothing, by the rule the parser reads a lesson's readings with ({@see ReadingLetters}): a Latin twin or a letter of
 * another Cyrillic alphabet inside a Cyrillic word becomes the letter of the readings. The parser mends a stored LESSON every
 * time it loads one, but the window of a day and its cards read `plan_terms`, written when the lesson was accepted: on 26.09
 * the owner's day 1 of «Собеседование» (ru→ro) still showed «а аҗута́» after §10.3 was deployed. No other field is read so —
 * a line, a translation, a definition is the model's text as written.
 *
 * `--dry-run` (the default) prints every field it would change — «таблица · id · колонка: было → стало», an invisible
 * character shown as ⟨U+XXXX⟩ — and changes nothing; `--apply` writes, in one transaction, after the database backup (as
 * for any write to the dev database). Idempotent: a second run finds nothing. The last line counts the rows fixed.
 */
final class PlanCleanTextCommand extends Command
{
    protected $signature = 'plan:clean-text
        {--dry-run : only print what would change (the default)}
        {--apply : write the cleaned texts}';

    protected $description = 'Cut the characters that print nothing out of the model\'s text already stored in the plan tables, and put the stored readings in the letters of the readings';

    /** Table → its columns of the model's text: `text` columns and `json` ones (read and written back as JSON). */
    private const COLUMNS = [
        'plans' => [
            'text' => ['title_native', 'title_target', 'event_native', 'until_phrase_native', 'overdue_native', 'cover_image_prompt', 'learner_role_target', 'learner_role_native', 'unclear_reason'],
            'json' => ['checks_json'],
        ],
        'plan_scenes' => [
            'text' => ['title_native', 'title_target', 'teaches_native', 'learner_role_target', 'learner_role_native', 'partner_role_target', 'partner_role_native', 'topic_description', 'image_prompt'],
            'json' => ['goals_native', 'lesson_json', 'checks_json'],
        ],
        'plan_terms' => [
            'text' => ['text_target', 'text_native', 'pronunciation_native', 'definition_target', 'example_target', 'example_native', 'speaking_key', 'image_prompt', 'frame_target', 'frame_native', 'frame_pronunciation_native'],
            'json' => ['simplified_variants', 'slot'],
        ],
        'day_cards' => [
            'text' => [],
            'json' => ['payload'],
        ],
    ];

    /** Table → its READING fields: `text` columns that are a reading, `json` columns whose {@see READING_KEYS} hold one. */
    private const READINGS = [
        'plan_terms' => ['text' => ['pronunciation_native', 'frame_pronunciation_native'], 'json' => ['slot']],
        'day_cards' => ['text' => [], 'json' => ['payload']],
    ];

    /** The keys a reading stands under in a JSON column: a filler's and a card's reading, a card's frame reading. */
    private const READING_KEYS = ['pronunciation_native', 'frame_pronunciation_native'];

    public function handle(): int
    {
        $apply = $this->option('apply') === true;
        if ($apply && $this->option('dry-run') === true) {
            $this->error('Either --dry-run or --apply, not both.');

            return self::FAILURE;
        }
        $normalizer = new TextNormalizer;

        $fixed = [];
        $fields = 0;
        $run = function () use ($apply, $normalizer, &$fixed, &$fields): void {
            foreach (self::COLUMNS as $table => $columns) {
                $fixed[$table] = 0;
                // The columns are this class's constants — no input reaches the SQL.
                $select = implode(', ', ['id', ...$columns['text'], ...array_map(static fn (string $c): string => "{$c}::text as {$c}", $columns['json'])]);
                foreach (DB::table($table)->selectRaw($select)->orderBy('id')->cursor() as $row) {
                    $update = [];
                    foreach ($columns['text'] as $column) {
                        $was = $row->{$column};
                        if (! is_string($was)) {
                            continue;
                        }
                        $now = $normalizer->visible($was);
                        if (in_array($column, self::READINGS[$table]['text'] ?? [], true)) {
                            $now = ReadingLetters::mended($now);
                        }
                        if ($now !== $was) {
                            $update[$column] = $now;
                            $this->report($apply, $table, (string) $row->id, $column, $was, $now);
                        }
                    }
                    foreach ($columns['json'] as $column) {
                        $was = $row->{$column};
                        $decoded = is_string($was) ? json_decode($was, true) : null;
                        if (! is_array($decoded)) {
                            continue;
                        }
                        $clean = ModelText::visible($decoded);
                        if (in_array($column, self::READINGS[$table]['json'] ?? [], true)) {
                            $clean = self::readingsMended($clean);
                        }
                        if ($clean !== $decoded) {
                            $update[$column] = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
                            $this->report($apply, $table, (string) $row->id, $column, self::changedStrings($decoded, $clean)[0], self::changedStrings($decoded, $clean)[1]);
                        }
                    }
                    if ($update === []) {
                        continue;
                    }
                    $fixed[$table]++;
                    $fields += count($update);
                    if ($apply) {
                        DB::table($table)->where('id', $row->id)->update($update);
                    }
                }
            }
        };
        $apply ? DB::transaction($run) : $run();

        $rows = array_sum($fixed);
        $this->line(sprintf(
            '%s rows: %d (%s) · fields: %d',
            $apply ? 'Fixed' : '[dry-run] Would fix',
            $rows,
            implode(', ', array_map(static fn (string $t, int $n): string => "{$t} {$n}", array_keys($fixed), $fixed)),
            $fields,
        ));

        return self::SUCCESS;
    }

    private function report(bool $apply, string $table, string $id, string $column, string $was, string $now): void
    {
        $this->line(sprintf('%s%s · %s · %s: «%s» → «%s»', $apply ? '' : '[dry-run] ', $table, $id, $column, self::shown($was), $now));
    }

    /**
     * A JSON value with every reading in it — a string under one of {@see READING_KEYS}, at any depth — read by
     * {@see ReadingLetters}; every other string as it is.
     *
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private static function readingsMended(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::readingsMended($item);
            } elseif (is_string($item) && in_array($key, self::READING_KEYS, true)) {
                $value[$key] = ReadingLetters::mended($item);
            }
        }

        return $value;
    }

    /** A text with every invisible character written out as ⟨U+XXXX⟩ — what the log can show of it. */
    private static function shown(string $text): string
    {
        return (string) preg_replace_callback(
            '/[\p{Cf}\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}-\x{009F}\x{2028}\x{2029}]/u',
            static fn (array $m): string => sprintf('⟨U+%04X⟩', mb_ord($m[0])),
            $text,
        );
    }

    /**
     * The first string of a JSON value the cleaning changed — before and after — for the log.
     *
     * @param  array<mixed>  $was
     * @param  array<mixed>  $now
     * @return array{0: string, 1: string}
     */
    private static function changedStrings(array $was, array $now): array
    {
        foreach ($was as $key => $value) {
            $other = $now[$key] ?? null;
            if (is_string($value) && is_string($other) && $value !== $other) {
                return [$value, $other];
            }
            if (is_array($value) && is_array($other) && $value !== $other) {
                return self::changedStrings($value, $other);
            }
        }

        return ['', ''];
    }
}
