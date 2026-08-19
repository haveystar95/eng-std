<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Where a piece of content came from: the prompt version that asked for it and the model that
 * wrote it. Carried by the row, not by the request, because terms are globally deduplicated —
 * a term created by one generation collects translations and examples from later ones, and
 * "which prompt wrote THIS line" is the only question a defect sweep can actually act on.
 *
 * Absent (`null` on the field that holds it) is a legitimate state: content that predates
 * provenance tracking, or content a human typed. It is never invented — a writer that does not
 * know the prompt version passes nothing rather than a guess.
 */
final readonly class Provenance
{
    public string $promptVersion;

    public ?string $model;

    /**
     * @param  string  $promptVersion  the versioned prompt file's version, e.g. `v10`
     * @param  string|null  $model  the model that answered, e.g. `gpt-4o` — null when the content
     *                              was produced without a model call (a cache-hit materialisation,
     *                              a deterministic repair)
     */
    public function __construct(string $promptVersion, ?string $model = null)
    {
        $version = trim($promptVersion);
        if ($version === '') {
            throw new InvalidArgumentException('Prompt version cannot be empty.');
        }
        $this->promptVersion = $version;

        $cleanModel = $model !== null ? trim($model) : null;
        $this->model = $cleanModel !== null && $cleanModel !== '' ? $cleanModel : null;
    }

    /**
     * Build one only if a prompt version is actually known — the shape every caller wants, since
     * "stamp it if you know it" is otherwise three lines at each call site.
     */
    public static function forOrNull(?string $promptVersion, ?string $model = null): ?self
    {
        if ($promptVersion === null || trim($promptVersion) === '') {
            return null;
        }

        return new self($promptVersion, $model);
    }
}
