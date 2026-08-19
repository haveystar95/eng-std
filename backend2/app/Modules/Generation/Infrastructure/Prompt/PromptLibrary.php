<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Prompt;

use App\Modules\Generation\Application\Dto\RenderedPrompt;
use App\Modules\Generation\Application\Port\PromptSource;
use App\Modules\Generation\Domain\ValueObject\PromptShape;
use InvalidArgumentException;
use RuntimeException;

/**
 * Where a prompt comes from, and what it is made of.
 *
 * ## Files, not a table
 *
 * A prompt version stays a file in this directory, under git, and the reasons are the ones that
 * decide it: a prompt is only correct together with the placeholders the adapter substitutes and the
 * JSON schema the answer is validated against, and those live in code. Move the text to a table and
 * the three can be edited independently — a row changed on the dev database while the schema that
 * must match it ships in a deploy, with no diff, no review and no way to tell afterwards which text
 * produced which content. Prompt changes are code changes here (the module skill says so), and
 * `prompt_version` on a content row is only trustworthy while the text it names cannot move under it.
 * The digest in {@see RenderedPrompt} closes the remaining gap: an edited file with an unbumped
 * version shows up as a different sha.
 *
 * ## Composed, not copied
 *
 * v1–v9 are single frozen files: one shape (a topic in, a term list out) and nothing else needed
 * them. v10 has three shapes — a term list, an enrichment of terms handed to it, and both at once —
 * and the rules that matter most (the translation-key isomorphism, both waves) are identical in all
 * three. Three files would mean three copies of the rule that the last two content sweeps were about,
 * and the copy that drifts is the one nobody re-reads. So v10 is a DIRECTORY of sections and each
 * shape is an ordered list of them. Each rule exists once; the shapes differ only in which sections
 * they include and in what order.
 *
 * Frozen versions are never edited — an old version is what makes a before/after comparison mean
 * anything.
 */
final class PromptLibrary implements PromptSource
{
    /** The composed version and the sections each shape is built from, in order. */
    private const COMPOSED = [
        'v10' => [
            PromptShape::Terms->value => [
                '00-role', '10-select-topic', '20-fields', '30-example',
                '40-translation-key', '50-purity', '90-self-check', '99-closing',
            ],
            PromptShape::Enrich->value => [
                '00-role', '15-given-terms', '20-fields', '60-options', '30-example',
                '40-translation-key', '50-purity', '90-self-check', '91-self-check-options', '99-closing',
            ],
            PromptShape::Full->value => [
                '00-role', '10-select-topic', '20-fields', '60-options', '30-example',
                '40-translation-key', '50-purity', '90-self-check', '91-self-check-options', '99-closing',
            ],
        ],
    ];

    public function __construct(private readonly string $directory = __DIR__) {}

    /**
     * The finished prompt text for this version and shape, with `{{placeholders}}` substituted.
     *
     * @param  array<string, string>  $placeholders  keys WITHOUT the braces, e.g. ['size' => '15']
     */
    public function render(string $version, PromptShape $shape, array $placeholders): RenderedPrompt
    {
        $template = $this->template($version, $shape);

        $replacements = [];
        foreach ($placeholders as $key => $value) {
            $replacements['{{' . $key . '}}'] = $value;
        }
        $text = strtr($template, $replacements);

        return new RenderedPrompt($text, $version, $shape, hash('sha256', $text));
    }

    /**
     * Every version this library can render, newest naming last — for a command's `--help` and tests.
     *
     * @return list<string>
     */
    public function versions(): array
    {
        $legacy = [];
        foreach (glob($this->directory . '/generate_collection.v*.md') ?: [] as $path) {
            if (preg_match('/generate_collection\.(v\d+)\.md$/', $path, $m) === 1) {
                $legacy[] = $m[1];
            }
        }
        usort($legacy, static fn (string $a, string $b): int => (int) substr($a, 1) <=> (int) substr($b, 1));

        return [...$legacy, ...array_keys(self::COMPOSED)];
    }

    /**
     * The shapes this version can be asked for. A frozen single-file version has exactly one.
     *
     * @return list<PromptShape>
     */
    public function shapesFor(string $version): array
    {
        if (! isset(self::COMPOSED[$version])) {
            return [PromptShape::Terms];
        }

        return array_map(
            static fn (string $shape): PromptShape => PromptShape::from($shape),
            array_keys(self::COMPOSED[$version]),
        );
    }

    /** The raw template, sections already joined, placeholders NOT yet substituted. */
    private function template(string $version, PromptShape $shape): string
    {
        if (! isset(self::COMPOSED[$version])) {
            if ($shape !== PromptShape::Terms) {
                throw new InvalidArgumentException(
                    "Prompt {$version} is a frozen single-file version and only has the '"
                    . PromptShape::Terms->value . "' shape; '{$shape->value}' was asked for."
                );
            }

            return $this->read($this->directory . "/generate_collection.{$version}.md");
        }

        // No "shape not found" branch: the map above declares every shape of every composed
        // version literally, so a missing one is a static error rather than a runtime one — PHPStan
        // refuses the null-check as unreachable, and refuses the map the day a shape is dropped.
        $sections = self::COMPOSED[$version][$shape->value];

        $parts = [];
        foreach ($sections as $section) {
            $parts[] = trim($this->read($this->directory . "/{$version}/{$section}.md"));
        }

        return implode("\n\n", $parts) . "\n";
    }

    private function read(string $path): string
    {
        $contents = is_file($path) ? file_get_contents($path) : false;
        if ($contents === false) {
            throw new RuntimeException("Prompt file not found: {$path}");
        }

        return $contents;
    }
}
