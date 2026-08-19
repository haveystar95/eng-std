<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $term_id
 * @property string $sentence
 * @property string|null $sentence_translation
 * @property string|null $source
 * @property string|null $prompt_version
 * @property string|null $generation_model
 */
final class TermExampleModel extends Model
{
    protected $table = 'term_examples';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];
}
