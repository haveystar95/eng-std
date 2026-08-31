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
    /**
     * The two prompts are versioned SEPARATELY, because they are revised separately: v0.2 took the
     * days out of the skeleton in one наряд step and the day's three arrays in the next, and a
     * shared constant would have stamped every day of that window with a version it was not
     * written at. v0.2.1 is that split earning its keep — the day prompt was rewritten alone,
     * after the live «собеседование» day failed twice on the frame rules, and P1 did not move.
     * v0.3 is the same split used a second time, for a bigger reason: after FOUR refusals in a row
     * the day prompt stopped asking the model to assemble a line at all — it now returns `frame`
     * and `filler` and the server pastes them together. P1 stayed at v0.2 through both.
     *
     * The v0.1.1 files stay in this directory as history and are not addressed by any constant —
     * a stored plan written on them is read back through its own `outline` JSON, not by
     * re-rendering the prompt, so nothing needs to load them again.
     */
    public const OUTLINE_VERSION = 'plan_outline.v0.2';

    public const DAY_VERSION = 'plan_day.v0.3';

    /**
     * P2R — the day's BROKEN CARDS, and nothing else.
     *
     * A third prompt rather than a third revision of P2, because it asks a different question. P2
     * writes a conversation out of a skeleton; P2R is handed a day that is already mostly accepted
     * and a short list of cards that failed a check, and returns those cards. Versioned separately
     * for the reason the other two are: it will move when the gates move, and P2 will not move
     * with it — and v0.2 is that split earning its keep on the second live day, with P1 and P2
     * untouched.
     *
     * **v0.2: a line is `frame` + `filler` TOGETHER.** v0.1 said a fixed card keeps its frame
     * unless the violation is in the frame — and the first live failure was exactly a broken
     * `filler` under a frame that no card of the day fits: «What time is ___?» with the day's own
     * chunk being «have breakfast» and not «breakfast». With the frame frozen there was no legal
     * move, so the answer took the letter of the rule and shipped «What time is two nights?»
     * (`docs/research/plan-v0.3.1-run.md`, «Отпуск в Италии»). Now the LINE is repaired, not the
     * field: the frame may be rewritten around a card that belongs in it. Plus one sentence saying
     * out loud that an `example` is a NEW sentence and DAY TERMS is a clone-check list rather than
     * the only vocabulary allowed — the same answer had swapped a word into an example to avoid a
     * clone and produced «I'll have pasta and two nights.»
     */
    public const REPAIR_VERSION = 'plan_day_repair.v0.2';

    private const OUTLINE = 'plan_outline.v0.2.md';
    private const DAY = 'plan_day.v0.3.md';
    private const REPAIR = 'plan_day_repair.v0.2.md';

    public function __construct(private readonly string $directory = __DIR__) {}

    public function outline(array $placeholders): RenderedPrompt
    {
        return $this->render(self::OUTLINE, self::OUTLINE_VERSION, $placeholders);
    }

    public function day(array $placeholders): RenderedPrompt
    {
        return $this->render(self::DAY, self::DAY_VERSION, $placeholders);
    }

    public function repair(array $placeholders): RenderedPrompt
    {
        return $this->render(self::REPAIR, self::REPAIR_VERSION, $placeholders);
    }

    public function outlineVersion(): string
    {
        return self::OUTLINE_VERSION;
    }

    public function dayVersion(): string
    {
        return self::DAY_VERSION;
    }

    public function repairVersion(): string
    {
        return self::REPAIR_VERSION;
    }

    /** @param array<string, string> $placeholders */
    private function render(string $file, string $version, array $placeholders): RenderedPrompt
    {
        $template = $this->body($this->read($this->directory . '/' . $file));

        $replacements = [];
        foreach ($placeholders as $key => $value) {
            $replacements['{{' . $key . '}}'] = $value;
        }
        $text = strtr($template, $replacements);

        // `Terms` because a plan prompt asks for a list of terms and the enum has no case for a
        // plan; the shape is carried so the DTO stays one type, and nothing switches on it here.
        return new RenderedPrompt($text, $version, PromptShape::Terms, hash('sha256', $text));
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
