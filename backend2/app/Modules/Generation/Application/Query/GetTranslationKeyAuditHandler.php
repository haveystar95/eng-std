<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Query;

use App\Modules\Collections\Application\Query\TermDeckTitleReader;
use App\Modules\Generation\Application\Dto\TranslationKeyAuditRow;
use App\Modules\Generation\Application\Dto\TranslationKeyAuditView;
use App\Modules\Vocabulary\Application\Query\AuditTranslationKeys;
use App\Modules\Vocabulary\Application\Query\AuditTranslationKeysHandler;

/**
 * Joins the two halves of the report, each asked of its owner: Vocabulary judges the key against its
 * own rule, Collections says which live decks ask it. Nothing here re-judges anything, and nothing
 * here touches another module's tables — that is the whole reason this layer exists rather than one
 * reader reaching across.
 */
final readonly class GetTranslationKeyAuditHandler
{
    public function __construct(
        private AuditTranslationKeysHandler $audit,
        private TermDeckTitleReader $decks,
    ) {}

    public function __invoke(GetTranslationKeyAudit $query): TranslationKeyAuditView
    {
        $judged = ($this->audit)(new AuditTranslationKeys($query->termLang, $query->sourceLang));

        // Only the CANDIDATES' decks are looked up: the clean pairs are not in the report, so their
        // titles are work nobody reads.
        $titles = $this->decks->titlesFor(
            array_map(static fn ($c): string => $c->termId, $judged->candidates),
        );

        $rows = array_map(
            static fn ($c): TranslationKeyAuditRow => new TranslationKeyAuditRow(
                termId: $c->termId,
                termText: $c->termText,
                kind: $c->kind,
                sourceText: $c->sourceText,
                lang: $c->lang,
                translation: $c->translation,
                groups: $c->groups,
                missingWords: $c->missingWords,
                expectedForms: $c->expectedForms,
                collections: $titles[$c->termId] ?? [],
            ),
            $judged->candidates,
        );

        return new TranslationKeyAuditView(
            seen: $judged->seen,
            rows: $rows,
            // Carried through Vocabulary's own view: the rule is its Domain, not something this
            // layer is allowed to reach into. Which languages it knows is the same kind of fact —
            // the report states it, Vocabulary decides it.
            groupNames: $judged->groupNames,
            seenTermsByLang: $judged->seenTermsByLang,
            seenExamplesByLang: $judged->seenExamplesByLang,
            skippedExamples: $judged->skippedExamples,
            ruleLanguages: $judged->ruleLanguages,
        );
    }
}
