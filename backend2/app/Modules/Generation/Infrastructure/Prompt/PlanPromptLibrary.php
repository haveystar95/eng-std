<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Prompt;

use App\Modules\Generation\Application\Dto\RenderedPrompt;
use App\Modules\Generation\Application\Port\PlanPromptSource;
use App\Modules\Generation\Domain\ValueObject\PromptShape;
use RuntimeException;

/**
 * The two plan prompts, read from files in this directory — for the reason
 * {@see PromptLibrary} states at length: a prompt is only correct together with the placeholders
 * the adapter substitutes and the schema its answer is validated against, and those live in code.
 *
 * ## The header is not part of the prompt
 *
 * Both files open with a Markdown header — what the file is, which registry row it is, what changed
 * since v0 — and end that header with a `---` rule. Everything above the FIRST such rule is
 * stripped before the text is sent, exactly as the sandbox runner stripped it, so what the model
 * reads is byte-for-byte what it read during the research run.
 *
 * That split is what lets the header be maintained without touching the prompt: a note about
 * provenance is for the person reading the file and is noise to the model, and a rule that has to
 * be re-explained in every prompt file is a rule that will be forgotten in one of them. The digest
 * on {@see RenderedPrompt} is over the sent text, so a header edit does not move it — which is the
 * check that this claim is true rather than merely intended.
 *
 * The FIRST rule only: both prompts use `---` again further down (before their DATA block), and
 * those separators are part of the prompt and stay.
 */
final class PlanPromptLibrary implements PlanPromptSource
{
    public const VERSION = 'plan.v0.1.1';

    private const OUTLINE = 'plan_outline.v0.1.1.md';
    private const DAY = 'plan_day.v0.1.1.md';

    public function __construct(private readonly string $directory = __DIR__) {}

    public function outline(array $placeholders): RenderedPrompt
    {
        return $this->render(self::OUTLINE, $placeholders);
    }

    public function day(array $placeholders): RenderedPrompt
    {
        return $this->render(self::DAY, $placeholders);
    }

    public function version(): string
    {
        return self::VERSION;
    }

    /** @param array<string, string> $placeholders */
    private function render(string $file, array $placeholders): RenderedPrompt
    {
        $template = $this->body($this->read($this->directory . '/' . $file));

        $replacements = [];
        foreach ($placeholders as $key => $value) {
            $replacements['{{' . $key . '}}'] = $value;
        }
        $text = strtr($template, $replacements);

        // `Terms` because a plan prompt asks for a list of terms and the enum has no case for a
        // plan; the shape is carried so the DTO stays one type, and nothing switches on it here.
        return new RenderedPrompt($text, self::VERSION, PromptShape::Terms, hash('sha256', $text));
    }

    /** Everything after the first `---` rule: the header above it is for readers, not for models. */
    private function body(string $raw): string
    {
        $parts = explode("\n---\n", $raw);
        if (count($parts) < 2) {
            throw new RuntimeException(
                'Файл промпта плана не содержит разделителя «---»: непонятно, где кончается шапка '
                . 'и начинается промпт, а отправить шапку в модель хуже, чем упасть.'
            );
        }

        return trim(implode("\n---\n", array_slice($parts, 1)));
    }

    private function read(string $path): string
    {
        $text = @file_get_contents($path);
        if ($text === false) {
            throw new RuntimeException("Файл промпта плана не читается: {$path}");
        }

        return $text;
    }
}
