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
        // v14 is v13.1 plus a THIRD product: near-synonyms of the term (`purpose` → `goal`, `aim`).
        //
        // It is a new product and not a rescue of `forms`. The forms field has returned an empty
        // list on every single machinery call since the shape went live — 217 of 217 answers between
        // 21.08 and 24.08, under v12.1 (which spent a whole section on it) and under v13.1 (which
        // spends three lines) alike — and that is the correct answer far more often than not: a real
        // term genuinely has no second SPELLING. What the collection screen was reporting as
        // «вариантов 0» is therefore not a broken pipeline, it is a product that had no data to
        // show, and the data a learner would recognise as «другие варианты» for `purpose` is `goal`
        // and `aim`, not `purpouse`.
        //
        // The section is written around ONE test the model can actually apply — substitute the
        // candidate into the card's own example and read the sentence back — because that is the
        // shape v13 established works on this model: a checkable instruction with a worked example
        // beats an essay about meaning. The deterministic half (a phrase gets none, a synonym is a
        // word or two, never the term itself) lives in EnrichmentValidator, where it can be tested
        // off the network.
        //
        // v13.1 is not edited: 183 live rows record it as their generator version.
        'v14' => [
            PromptShape::Machinery->value => [
                '00-role', '16-given-core', '25-fields-machinery', '61-distractors', '63-synonyms',
                '99-closing',
            ],
        ],
        // v14.1 fixes v14 by the same lesson v13 was written from, which v14 then walked straight
        // back into: this model answers the instruction it reads FIRST and drowns in a long one.
        //
        // v14's synonym section sat AFTER the ~900-word distractor block, and it said «zero is a
        // perfectly good answer», «fewer is better than looser» and «a paraphrase is discarded»
        // before it ever showed what a good answer looks like. Measured on a 20-term pilot over «В
        // банке»: 89 distractor candidates proposed, 49 written — and `synonyms: []` on every single
        // term, including `debit card`, `credit card` and `bank account`. `debit card` → `bank card`
        // is a synonym the OLD prompt used to return unprompted (it is a live row in
        // `term_accepted_variants`), so this was the prompt suppressing an answer the model has.
        //
        // Exactly the shape of the `forms` failure this наряд diagnosed — buried section, tripled
        // discouragement, no worked example — reproduced by the section written to replace it.
        //
        // So: the section moves BEFORE the distractors, `25-fields` lists the fields in that same
        // order, and the text leads with three worked substitutions instead of with a warning. The
        // strictness is kept, but stated ONCE and at the end, where v13 puts its counter-rules.
        //
        // v14 is not edited: 20 live terms record it as their generator version.
        'v14.1' => [
            PromptShape::Machinery->value => [
                '00-role', '16-given-core', '25-fields-machinery', '63-synonyms', '61-distractors',
                '99-closing',
            ],
        ],
        // v14.2 adds the SECOND test, and it is the one that was missing rather than a rewording.
        //
        // v14.1's rule was «substitute the candidate into the card's own example and read it back».
        // That works and it is not sufficient: a NARROWER word passes it every time, because the
        // example is a sentence about the term and a type of the term fits a sentence about the
        // term. Measured on the same twenty terms of «В банке»: 15 synonyms written, and five of
        // them were narrower or simply a different product — `bank account` → `savings account` and
        // `checking account`, `credit card` → `charge card`, `withdrawal limit` → `cash withdrawal
        // limit`, `direct debit` → `automatic payment`. One in three, on a field that is an ACCEPTED
        // ANSWER: a card teaching «банковский счёт = savings account».
        //
        // The cure is not more prohibition. It is the test the RETIRED `forms` section already had
        // and this наряд failed to carry over: cover the target side, read only the translation, and
        // ask whether a competent speaker would answer THAT with your word. `savings account` fails
        // it instantly. Its three worked counter-examples come back with it — they were paid for by
        // an earlier run and they are about exactly this failure.
        //
        // v14.1 is not edited: 15 live rows record it as their generator version.
        'v14.2' => [
            PromptShape::Machinery->value => [
                '00-role', '16-given-core', '25-fields-machinery', '63-synonyms', '61-distractors',
                '99-closing',
            ],
        ],
        // v14.3 is v14.2 with the synonym section TAKEN OUT, and it is a deletion rather than a
        // fourth attempt at the product.
        //
        // The станок stopped WRITING synonyms in code: {@see \App\Modules\Generation\Application\Command\BuildTermEnrichmentsHandler}
        // imports `synonyms: []` unconditionally, because a synonym is a CORE product from v15 on and
        // one table wants one producer. The prompt, though, went on ASKING — ~600 words of section on
        // the way in and a list of up to three on the way out, bought on every term and dropped on the
        // floor by the very next statement. That is the whole change: the model is no longer asked a
        // question whose answer has nowhere to go.
        //
        // `61-distractors` and `99-closing` are byte-identical copies of v14.2's. The other three lose
        // only the clauses that named the field — the role's list of products, the job description,
        // and the field list — because a prompt that describes a field the schema does not carry is
        // the exact defect v13 was rescued from (a section the model obeys against a contract that
        // cannot hold it).
        //
        // The schema stops declaring `synonyms` at this version too, in {@see \App\Modules\Generation\Application\Service\ContentContract}:
        // strict Structured Outputs makes every declared property required, so leaving it in would
        // force the model to emit a field this text never mentions.
        //
        // v14.2 is not edited: 27 live rows record it as their generator version.
        'v14.3' => [
            PromptShape::Machinery->value => [
                '00-role', '16-given-core', '25-fields-machinery', '61-distractors', '99-closing',
            ],
        ],
        // v15 is the CORE, and it is where synonyms end up after the machinery failed to hold them.
        //
        // Three measured iterations on `gpt-4o-mini` (v14 → v14.2, docs/syn-1-findings.md §7) put
        // the accuracy of «synonym vs narrower word» at about half, and the last of them produced
        // `savings account` for «банковский счёт» after being shown that exact pair as a worked
        // failure. The judgement is not the prompt's to fix: it is what a strong model is for, and
        // the core call is already made by one (`gpt-5.4`, DECISIONS п. 60). So the field moves to
        // the writer who can answer it, and the machinery keeps the two products it does well —
        // wrong sentences and accepted spellings.
        //
        // v15 also carries the two other per-pair products this наряд adds, for the same reason:
        // `other_translations` (a word's other readings) and `transliteration` (how the term reads,
        // in the SUPPORT language's letters). Both are judgements about meaning or about how a word
        // sounds to a particular reader, and both are cheapest where the core is already being
        // written rather than as a second paid pass.
        //
        // The v14 lesson is obeyed in the layout: `21-extras` sits immediately after the field list,
        // not at the end behind the long example and key sections. The section that gets read last
        // gets answered with an empty list.
        //
        // v11.1 is not edited: it is what the published A/B measured and what the live catalogue
        // records as its passport.
        'v15' => [
            PromptShape::Terms->value => [
                '00-role', '10-select-topic', '20-fields', '21-extras', '30-example',
                '40-translation-key', '50-purity', '90-self-check', '99-closing',
            ],
            // The REPAIR shape carries the same three extras, and it has to: it is what the showcase
            // regeneration and the translation audit render at the CORE version, so a v15 without it
            // would make those paths ask for a shape their version does not have. Same sections as
            // v11.1's enrich, plus `21-extras` in the same early position.
            PromptShape::Enrich->value => [
                '00-role', '15-given-terms', '20-fields', '21-extras', '60-options', '62-forms',
                '30-example', '40-translation-key', '50-purity', '90-self-check',
                '91-self-check-options', '99-closing',
            ],
        ],
        // v15.1 changes ONE section — `21-extras` — and the change is about SELECTIVITY rather than
        // about how a synonym is written.
        //
        // v15's pilot measured the strong model at 67% clean synonyms, 26% arguable and 7% wrong
        // (docs/syn-1-findings.md §8), against a threshold of ≥85% clean and ≤5% wrong. The wrong
        // ones were not sloppiness: `jealous` → `envious`, `cheerful` → `bright`, `surprised` →
        // `amazed` are a model doing its best to produce SOMETHING on an item whose honest answer is
        // nothing. v15 asked for «0–3» and explained how to choose well; it never said that empty is
        // the ordinary outcome, so the field read as a slot to fill.
        //
        // So the section now leads with the default — an empty list, and «if you hesitate, do not
        // write it» in those words — and gains a third test (same strength, same register) beside
        // the two that were already there, because that is what the arguable 26% failed: `nice` and
        // `caring` for «добрый», `happy` for «жизнерадостный», `amazed` for «удивлённый». Multi-word
        // terms and phrasal verbs are told outright that empty is the expected answer for them, and
        // `other_translations` is narrowed from «ambiguous» to «a different MEANING, of the same part
        // of speech as the card» — the reading that produced «опрокинутый» for the adjective `upset`.
        //
        // Deliberately NOT changed: the transliteration section, byte for byte. It measured 49/49 in
        // the same pilot, its switch is on, and a working product is not edited beside a broken one.
        //
        // v15 is not edited: the pilots that measured it are what the threshold decision rests on,
        // and 49 live reading hints record it as their generator version.
        'v15.1' => [
            PromptShape::Terms->value => [
                '00-role', '10-select-topic', '20-fields', '21-extras', '30-example',
                '40-translation-key', '50-purity', '90-self-check', '99-closing',
            ],
            PromptShape::Enrich->value => [
                '00-role', '15-given-terms', '20-fields', '21-extras', '60-options', '62-forms',
                '30-example', '40-translation-key', '50-purity', '90-self-check',
                '91-self-check-options', '99-closing',
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
