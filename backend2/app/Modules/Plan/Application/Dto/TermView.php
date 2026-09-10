<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/** A word, chunk or phrase on the sheet. */
final readonly class TermView
{
    /**
     * @param  list<string>  $simplifiedVariants
     * @param  array{url: string, author: string|null, author_url: string|null}|null  $image
     */
    public function __construct(
        public string $id,
        public string $sceneId,
        public string $kind,
        public string $ref,
        public string $textTarget,
        public string $textNative,
        public ?string $pronunciationNative,
        public ?string $definitionTarget,
        public ?string $exampleTarget,
        public ?string $exampleNative,
        public ?string $speakingKey,
        public array $simplifiedVariants,
        public ?array $image,
    ) {}
}
