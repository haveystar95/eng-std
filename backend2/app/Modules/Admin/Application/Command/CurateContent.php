<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Command;

/**
 * One content curation act, as issued from the panel.
 *
 * A single command object for all six because they share everything that matters — an admin, a
 * target, an audit line — and differ only in which of the target's fields move. Splitting them
 * into six near-identical classes would buy nothing but six places to forget the audit call.
 */
final readonly class CurateContent
{
    public const EDIT_TERM = 'term.edit';
    public const RETIRE_TERM = 'term.retire';
    public const EDIT_COLLECTION = 'collection.edit';
    public const ADD_TERM_TO_COLLECTION = 'collection.term.add';
    public const REMOVE_TERM_FROM_COLLECTION = 'collection.term.remove';
    public const DELETE_COLLECTION = 'collection.delete';

    /** @param array<string, mixed> $fields */
    public function __construct(
        public string $adminId,
        public string $action,
        public ?string $termId = null,
        public ?string $collectionId = null,
        public array $fields = [],
    ) {}
}
