<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Prompt;

use App\Modules\Plan\Application\Dto\LessonCardRepairRequest;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\NativeSeamJudgeRequest;
use App\Modules\Plan\Application\Dto\PlanRequest;
use App\Modules\Plan\Application\Dto\SlotJudgeRequest;
use RuntimeException;

/**
 * THE PLAN'S PROMPT FILES, read from this directory. The version of each is its file stem
 * (`plan-builder-v2`, `lesson_day.v4.5`) — a rename is a version bump and nothing else is.
 *
 * The files are frozen: nothing here edits their text. Each ends with a «TEST INPUT» section the
 * author used to try the prompt by hand; that section is cut out and the real inputs go in the
 * user message, named exactly as the prompt's INPUTS section names them, so the rules and the data
 * travel on different channels.
 */
final class PlanPromptFiles
{
    private const PLAN_FILE = 'plan-builder-v2.md';

    private const LESSON_FILE = 'lesson_day.v4.5.md';

    private const REPAIR_FILE = 'lesson_card_repair.v1.1.md';

    private const JUDGE_FILE = 'lesson_seam_judge.v1.1.md';

    /** The slot judge of the day's spoken cards (наряд SESSION-1a, разд. 4) — accepted byte for byte from the order. */
    public const SLOT_JUDGE_FILE = 'slot_judge.v1.md';

    /**
     * The sections of the lesson prompt a repair of each card kind quotes — by the start of their
     * heading, word for word: the repair wrapper never retells a rule. A whole exchange answers to every
     * rule a turn of the visit has: its kind, its place in the visit, both lines and its check.
     */
    private const REPAIR_SECTIONS = [
        'frame' => ['LEVEL', 'FRAMES', 'TEXT QUALITY', 'PRONUNCIATION_NATIVE'],
        'exchange' => [
            'LEVEL', 'EXCHANGE KINDS', 'NATURAL ORDER OF ONE VISIT', 'MOBILE-FRIENDLY MESSAGE LENGTH', 'CONVERSATION PARTNER RULE',
            'LEARNER MESSAGES', 'TEXT QUALITY', 'PRONUNCIATION_NATIVE', 'CHECK PER EXCHANGE',
        ],
        'line' => ['LEVEL', 'EXCHANGE KINDS', 'MOBILE-FRIENDLY MESSAGE LENGTH', 'LEARNER MESSAGES', 'TEXT QUALITY', 'PRONUNCIATION_NATIVE'],
        'check' => ['LEVEL', 'CHECK PER EXCHANGE'],
        'listening' => ['LISTENING'],
    ];

    private const TEST_INPUT_MARKER = "\n---\n\nTEST INPUT\n";

    private const FINAL_RULE_MARKER = "\n---\n\nFINAL OUTPUT RULE\n";

    /** @var array<string, string> */
    private array $texts = [];

    public function __construct(private readonly string $directory = __DIR__) {}

    public function planVersion(): string
    {
        return pathinfo(self::PLAN_FILE, PATHINFO_FILENAME);
    }

    public function lessonVersion(): string
    {
        return pathinfo(self::LESSON_FILE, PATHINFO_FILENAME);
    }

    public function repairVersion(): string
    {
        return pathinfo(self::REPAIR_FILE, PATHINFO_FILENAME);
    }

    public function judgeVersion(): string
    {
        return pathinfo(self::JUDGE_FILE, PATHINFO_FILENAME);
    }

    public function slotJudgeVersion(): string
    {
        return pathinfo(self::SLOT_JUDGE_FILE, PATHINFO_FILENAME);
    }

    /**
     * P2R's rules for one card kind: the repair wrapper with the lesson prompt's own sections for that
     * kind quoted in place of `{{rules}}`.
     *
     * @param  'frame'|'exchange'|'line'|'check'|'listening'  $kind
     */
    public function repairSystem(string $kind): string
    {
        $sections = array_map(fn (string $heading): string => $this->lessonSection($heading), self::REPAIR_SECTIONS[$kind]);

        return str_replace('{{rules}}', implode("\n\n---\n\n", $sections), $this->text(self::REPAIR_FILE));
    }

    /**
     * The repair's data: the inputs, the card's address and kind, what is broken, the card, and only the part of
     * the lesson this card needs (P2R v1.1, наряд GEN-2b) — the day's frames and words and the lines around it.
     */
    public function repairUser(LessonCardRepairRequest $request): string
    {
        $lines = [
            'TARGET_LANGUAGE: '.$request->targetLanguage,
            'NATIVE_LANGUAGE: '.$request->nativeLanguage,
            'LEVEL: '.$request->level->promptLabel(),
            'LEARNER_GENDER: '.($request->learnerGender->value ?? 'unknown'),
            '',
            'ADDRESS: '.$request->address,
            'CARD KIND: '.$request->kind,
            '',
            'FINDINGS (code · what is broken):',
        ];
        foreach ($request->findings as $finding) {
            $lines[] = '- '.$finding['code'].' · '.self::oneLine($finding['detail']);
        }

        return implode("\n", [
            ...$lines,
            '',
            'CARD (as written):',
            self::json($request->card, pretty: true),
            '',
            'LESSON (accepted; only the part this card needs — the day\'s frames and words, the lines around the card; for context, do not return it):',
            self::json($request->context),
        ]);
    }

    /** The seam judge's rules — the file as it is. */
    public function judgeSystem(): string
    {
        return $this->text(self::JUDGE_FILE);
    }

    /** The seam judge's data: the learner's language by name and every sentence to read, with its id. */
    public function judgeUser(NativeSeamJudgeRequest $request): string
    {
        return implode("\n", [
            'NATIVE_LANGUAGE: '.$request->nativeLanguage,
            '',
            'ITEMS (id · the pattern with its slot · the value put into the slot · the sentence they make):',
            self::json($request->items),
        ]);
    }

    /** The slot judge's rules — the file as it is. */
    public function slotJudgeSystem(): string
    {
        return $this->text(self::SLOT_JUDGE_FILE);
    }

    /**
     * The slot judge's data: one line per INPUT of the prompt, in its order, each value as it is — the recogniser's
     * text is not collapsed, because a doubled word or a stray mark is the recognition noise the prompt tells the
     * model to forgive, and the model can only forgive what it sees.
     */
    public function slotJudgeUser(SlotJudgeRequest $request): string
    {
        return implode("\n", [
            'TASK: '.$request->task,
            'TARGET_LANGUAGE: '.$request->targetLanguage,
            'NATIVE_LANGUAGE: '.$request->nativeLanguage,
            'LEVEL: '.$request->level,
            'PARTNER_LINE: '.$request->partnerLine,
            'PARTNER_LINE_NATIVE: '.$request->partnerLineNative,
            'PATTERN: '.$request->pattern,
            'PATTERN_NATIVE: '.$request->patternNative,
            'SLOT_HINT: '.$request->slotHint,
            'EXAMPLE_VALUES: '.$request->exampleValues,
            'HEARD: '.$request->heard,
        ]);
    }

    /**
     * One section of the lesson prompt, heading included: the part between two `---` separators whose
     * first line starts with `$heading`.
     */
    public function lessonSection(string $heading): string
    {
        foreach (explode("\n---\n", $this->text(self::LESSON_FILE)) as $part) {
            $part = trim($part);
            if (str_starts_with($part, $heading)) {
                return $part;
            }
        }

        throw new RuntimeException("Lesson prompt has no section «{$heading}»");
    }

    /** The plan builder's rules — the file without its TEST INPUT tail. */
    public function planSystem(): string
    {
        return $this->text(self::PLAN_FILE);
    }

    /** The lesson generator's rules — the file with its TEST INPUT section cut out, FINAL OUTPUT RULE kept. */
    public function lessonSystem(): string
    {
        return $this->text(self::LESSON_FILE);
    }

    public function planUser(PlanRequest $request): string
    {
        $lines = [
            'GOAL: '.self::oneLine($request->goal),
            'TARGET_LANGUAGE: '.$request->targetLanguage,
            'NATIVE_LANGUAGE: '.$request->nativeLanguage,
            'LEVEL: '.$request->level->promptLabel(),
            'SCENES_COUNT: '.$request->scenesCount,
        ];
        if ($request->existingScenes === []) {
            $lines[] = 'EXISTING_SCENES:';
        } else {
            $lines[] = 'EXISTING_SCENES:';
            foreach ($request->existingScenes as $title) {
                $lines[] = '- '.self::oneLine($title);
            }
        }

        return implode("\n", $lines).self::violations($request->previousViolations);
    }

    public function lessonUser(LessonRequest $request): string
    {
        $lines = [
            'TOPIC: '.self::oneLine($request->topic),
            '',
            'TOPIC_DESCRIPTION: '.trim($request->topicDescription),
            '',
            'TARGET_LANGUAGE: '.$request->targetLanguage,
            '',
            'NATIVE_LANGUAGE: '.$request->nativeLanguage,
            '',
            'LEVEL: '.$request->level->promptLabel(),
            '',
            'LEARNER_GENDER: '.$request->learnerGenderInput(),
            '',
            'VOCABULARY_COUNT: '.$request->vocabularyCount,
            '',
            'DIALOGUE_COUNT: '.$request->dialogueCount,
        ];

        return implode("\n", $lines).self::violations($request->previousViolations);
    }

    /**
     * The one retry quotes what the previous answer was refused for — as data after the inputs,
     * never as a change to the rules.
     *
     * @param  list<string>  $violations
     */
    private static function violations(array $violations): string
    {
        if ($violations === []) {
            return '';
        }
        $lines = ["\n\nPREVIOUS_ATTEMPT_REJECTED_FOR:"];
        foreach ($violations as $violation) {
            $lines[] = '- '.self::oneLine($violation);
        }

        return implode("\n", $lines);
    }

    private static function oneLine(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * JSON for the model: the card pretty, the context and the items one compact line per entry — what the model
     * reads without paying for indentation.
     *
     * @param  array<array-key, mixed>  $value
     */
    private static function json(array $value, bool $pretty = false): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
        if ($pretty) {
            return json_encode($value, $flags | JSON_PRETTY_PRINT);
        }
        if (! array_is_list($value)) {
            $lines = [];
            foreach ($value as $key => $entry) {
                $lines[] = json_encode((string) $key, $flags).': '.(is_array($entry) ? self::json($entry) : json_encode($entry, $flags));
            }

            return "{\n".implode(",\n", $lines)."\n}";
        }

        return "[\n".implode(",\n", array_map(static fn (mixed $entry): string => json_encode($entry, $flags), $value))."\n]";
    }

    private function text(string $file): string
    {
        if (isset($this->texts[$file])) {
            return $this->texts[$file];
        }
        $path = rtrim($this->directory, '/').'/'.$file;
        $raw = @file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException("Prompt file not found: {$path}");
        }

        return $this->texts[$file] = self::withoutTestInput($raw);
    }

    /**
     * Cut the TEST INPUT section: everything from its heading to the next section heading
     * (FINAL OUTPUT RULE) when there is one after it, else to the end of the file.
     */
    private static function withoutTestInput(string $raw): string
    {
        $start = strpos($raw, self::TEST_INPUT_MARKER);
        if ($start === false) {
            return rtrim($raw)."\n";
        }
        $end = strpos($raw, self::FINAL_RULE_MARKER, $start + strlen(self::TEST_INPUT_MARKER));
        $cut = $end === false
            ? substr($raw, 0, $start)
            : substr($raw, 0, $start).substr($raw, $end);

        return rtrim($cut)."\n";
    }
}
