<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * A deliberately-broken variant of an example, for the find-the-mistake trainer. `errorSpan` is the
 * exact substring of `sentence` that is wrong, so the panel can highlight it in place instead of
 * making the reader diff two sentences by eye.
 */
final readonly class ExampleDistractorRow
{
    public function __construct(
        public string $id,
        public string $sentence,
        public string $errorType,   // article | preposition | tense | word_order | false_friend | modal_to
        public string $errorSpan,
        public string $correction,
        public string $generatorVersion,
    ) {}
}
