<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Prompt;

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\PlanRequest;
use RuntimeException;

/**
 * THE TWO PROMPT FILES, read from this directory. The version of each is its file stem
 * (`plan-builder-v2`, `lesson-v3`) — a rename is a version bump and nothing else is.
 *
 * The files are frozen: nothing here edits their text. Each ends with a «TEST INPUT» section the
 * author used to try the prompt by hand; that section is cut out and the real inputs go in the
 * user message, named exactly as the prompt's INPUTS section names them, so the rules and the data
 * travel on different channels.
 */
final class PlanPromptFiles
{
    private const PLAN_FILE = 'plan-builder-v2.md';

    private const LESSON_FILE = 'lesson-v3.md';

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
            'PHRASES_COUNT: '.$request->phrasesCount,
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
