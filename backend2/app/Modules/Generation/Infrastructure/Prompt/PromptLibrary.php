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
 * anything. A version therefore carries its OWN copy of every section it uses, even where the text
 * is identical to a neighbour's: a shared file would let an edit to one version move another's
 * rendered bytes under a `prompt_version` that has already been recorded on live content.
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
        'v11' => [
            // The COLLECTION shape: a core and nothing else — term, key, one example. No options,
            // because a core is what a human reviews and machinery is what a cheaper model can add
            // afterwards over a core that survived review.
            PromptShape::Terms->value => [
                '00-role', '10-select-topic', '20-fields', '30-example',
                '40-translation-key', '50-purity', '90-self-check', '99-closing',
            ],
            // The MECHANICS shape: finished cards in, options and forms out. It is forbidden to
            // touch the core, so it carries none of the rules about writing one.
            PromptShape::Mechanics->value => [
                '00-role', '16-given-core', '25-fields-mechanics', '60-options', '62-forms',
                '50-purity', '92-self-check-mechanics', '99-closing',
            ],
            // The REPAIR shape: a bare term in, core AND machinery out. The path for regenerating
            // the showcase and for "learn this word".
            PromptShape::Enrich->value => [
                '00-role', '15-given-terms', '20-fields', '60-options', '62-forms', '30-example',
                '40-translation-key', '50-purity', '90-self-check', '91-self-check-options', '99-closing',
            ],
            // The ONE-SHOT shape: a topic in, a finished collection out. The experiment.
            PromptShape::Full->value => [
                '00-role', '10-select-topic', '20-fields', '60-options', '30-example',
                '40-translation-key', '50-purity', '90-self-check', '91-self-check-options', '99-closing',
            ],
        ],
        // v11.1 is v11 plus ONE rule, in the section that owns the key: a translation is the form a
        // living person says, not the grammatically faithful rendering of the target word's shape.
        // The run-in collections found the defect on state adjectives, where the faithful rendering
        // is a participle — `relieved` → «испытавший облегчение», which nobody utters and no learner
        // can produce it back from. The definition rule beside it does not catch these: they are
        // short, they contain no explanatory connective, and they are perfectly accurate.
        //
        // v11 is not edited: it is what the published A/B compared and what 39 live terms record as
        // their passport.
        'v11.1' => [
            PromptShape::Terms->value => [
                '00-role', '10-select-topic', '20-fields', '30-example',
                '40-translation-key', '50-purity', '90-self-check', '99-closing',
            ],
            PromptShape::Mechanics->value => [
                '00-role', '16-given-core', '25-fields-mechanics', '60-options', '62-forms',
                '50-purity', '92-self-check-mechanics', '99-closing',
            ],
            PromptShape::Enrich->value => [
                '00-role', '15-given-terms', '20-fields', '60-options', '62-forms', '30-example',
                '40-translation-key', '50-purity', '90-self-check', '91-self-check-options', '99-closing',
            ],
            PromptShape::Full->value => [
                '00-role', '10-select-topic', '20-fields', '60-options', '30-example',
                '40-translation-key', '50-purity', '90-self-check', '91-self-check-options', '99-closing',
            ],
        ],
        // v12 exists for ONE shape, and that is the honest description of it: the machinery this app
        // actually stores. v11's `mechanics` returns wrong TRANSLATIONS — a real product with no
        // table under it here (the trainer builds meaning options out of neighbouring terms for
        // free) — and it has no rules at all for the wrong SENTENCES `pick_correct` runs on, which
        // only a model can write. Those rules exist, in `enrich_pack.v2`, and this version is where
        // they move into the catalogue so production stops paying for one product to get the other.
        //
        // v11 is not edited to add them: it is what the published A/B compared, and a section added
        // to it would change nothing about the rendered bytes of the shapes that were measured but
        // would make «v11» name two different libraries. A version is cheaper than that ambiguity.
        'v12' => [
            PromptShape::Machinery->value => [
                '00-role', '16-given-core', '25-fields-machinery', '62-forms', '61-distractors',
                '93-self-check-machinery', '99-closing',
            ],
        ],
        // v12.1 is v12 asking for MORE distractor candidates than a card can hold — four or five
        // instead of two or three. The validator is deterministic and throws out roughly half of
        // what comes back (a "wrong" sentence our own grader accepts, a span that is not in its own
        // sentence, a correction that does not repair), so asking for exactly what fits produced
        // cards with one option and no question: on the two run-in collections, 28 of 40 pinned
        // examples could not host a `pick_correct` at all.
        //
        // The same shape as the ×1.3 overshoot on a term list ({@see GenerationPipeline}), and for
        // the same reason: over-order the cheap input to a filter whose scrap rate is known. The
        // rule the section keeps is the one that makes it safe — a candidate invented to reach the
        // count is a sentence that is not wrong, and «пустые формы лучше набивки» still binds.
        'v12.1' => [
            PromptShape::Machinery->value => [
                '00-role', '16-given-core', '25-fields-machinery', '62-forms', '61-distractors',
                '93-self-check-machinery', '99-closing',
            ],
        ],
        // v13 is v12.1 rewritten around WHERE the losses actually are, measured rather than assumed.
        //
        // Replaying a whole run's model answers through the validator (13 terms, 44 candidates) put
        // every scrapped row against the check that scrapped it. Nothing died of the rule the long
        // prompt spends half its length on — `equals_accepted_answer`, the "secretly correct"
        // distractor, killed ZERO rows. A third died on the three mechanical fields (span not in its
        // own sentence, correction that does not repair, correction swallowing the final mark), which
        // v12.1 explains in a hundred and fifty words at the end of a list of thirty rules.
        //
        // So this version leads with the mechanical contract and gives it a worked example, and it
        // states as rules only what a real run has been observed to get wrong. gpt-4o-mini on v12.1
        // broke that prompt's own explicit prohibitions — markdown around the error in four rows of
        // five — and produced zero usable distractors for `next to`; on a short prompt of this shape
        // the same model produced three. The length was not buying compliance, it was drowning it.
        //
        // What survives from v12.1 is the part that turned out to be load-bearing, kept as five short
        // counter-rules instead of essays: a different TENSE is not an error (dropping this rule
        // immediately produced «The post office was next to the museum» as a "distractor"), a swapped
        // determiner usually is not, a changed meaning is not, a typo is not, a re-spelled
        // contraction is not.
        //
        // `forms` loses its section: v12/v12.1 wrote ZERO accepted forms across 81 terms, so 311
        // words were being bought on every call for a product that never arrives. The field stays in
        // the schema and gets three lines saying an empty list is the normal answer.
        'v13' => [
            PromptShape::Machinery->value => [
                '00-role', '16-given-core', '25-fields-machinery', '61-distractors', '99-closing',
            ],
        ],
        // v13.1 fixes ONE thing, and the thing was ours. v13's worked example laid the fields out as
        // a pseudo-table with the values in quotes:
        //
        //     error_span:  "next the"
        //
        // and the model copied the quotes INTO the value — `"error_span": "\"place to\""`. A span
        // wrapped in a character that is not in the sentence cannot be found in it, so the row died
        // at `span_not_found`: 12 of 45 candidates in the first v13 run, 27%, every one of them a
        // perfectly good wrong sentence thrown away over punctuation we taught it to add.
        //
        // The example is now the JSON object the answer actually consists of, where quotes are
        // unambiguously syntax, plus one line saying the three fields are plain text. v13 is not
        // edited in place: 22 live rows already record it as their prompt version, and a version that
        // names two different texts is worse than an extra directory.
        'v13.1' => [
            PromptShape::Machinery->value => [
                '00-role', '16-given-core', '25-fields-machinery', '61-distractors', '99-closing',
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

        // v11 added a shape v10 does not have, so this lookup can genuinely miss and says so
        // instead of resolving to an undefined index. (While every version had every shape PHPStan
        // refused this branch as unreachable — it is the analyzer, not the author, that decides
        // which of the two states we are in.)
        $sections = self::COMPOSED[$version][$shape->value] ?? throw new InvalidArgumentException(
            "Prompt {$version} has no '{$shape->value}' shape; it has: "
            . implode(', ', array_keys(self::COMPOSED[$version])) . '.'
        );

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
