<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Exception;

use RuntimeException;

/**
 * The model's JSON is not the shape the prompt asked for. Not a check — a refusal: the caller
 * asks once more and, if that fails too, the build is `failed`.
 */
final class ModelAnswerOffSchema extends RuntimeException
{
    public static function at(string $path, string $why): self
    {
        return new self("Ответ модели не по схеме: {$path} — {$why}.");
    }
}
