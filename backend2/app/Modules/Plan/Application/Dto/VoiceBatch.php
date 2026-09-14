<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * One vendor call of a scene's voice (DAY-UI-3): what is said — the whole dialogue even when one line
 * of it is missing, so the conversation keeps its two voices and its rhythm — and which of the lines
 * are owed and get stored.
 */
final readonly class VoiceBatch
{
    public const DIALOGUE = 'dialogue';
    public const PHRASES = 'phrases';
    public const WORDS = 'words';

    /**
     * @param  list<LineToSay>  $lines
     * @param  list<string>  $owed  refs to keep from the answer
     */
    public function __construct(
        public string $kind,
        public array $lines,
        public array $owed,
    ) {}
}
