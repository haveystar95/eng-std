<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Port;

use App\Modules\Vocabulary\Application\Dto\RepairedTranslationRow;

/**
 * Reads and rewrites the LABEL on a translation row — `lang`, never the text.
 *
 * Separate from {@see TermCurator} on purpose: curation edits what a translation SAYS, and every one
 * of its writes is a content decision a person made. This one asserts nothing about content; it
 * corrects rows whose text was already replaced by the retrospective language repair while their
 * label was left behind.
 */
interface TranslationLabelWriter
{
    /**
     * Every translation row NOT labelled `$lang`, with what the handler needs to judge it.
     *
     * @return list<RepairedTranslationRow>
     */
    public function rowsNotLabelled(string $lang): array;

    /**
     * Relabel these rows to `$lang` and bump their terms' `updated_at`.
     *
     * The bump is not optional: the delta sync detects a changed term by that column alone, and a
     * relabel that the phone never hears about leaves the old mirror in place — which for a device
     * that already pulled the wrongly-labelled row means the fix never arrives.
     *
     * @param  list<string>  $rowIds
     * @return int  rows actually relabelled
     */
    public function relabel(array $rowIds, string $lang): int;
}
