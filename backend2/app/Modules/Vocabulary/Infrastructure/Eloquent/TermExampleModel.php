<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $term_id
 * @property string $lang                        the sentence's own language = the term's
 * @property string $sentence
 * @property string|null $sentence_translation    DEPRECATED — see below
 * @property string|null $source
 * @property string|null $prompt_version
 * @property string|null $generation_model
 *
 * DEPRECATED: `sentence_translation`. An example translation is written IN A LANGUAGE, and this
 * column could never say which — it held whatever language the collection that first pulled the
 * term in happened to support. It now lives in `example_translations`, one row per language, the
 * same shape `term_translations` has always had. The column is retained (not dropped) until phase A
 * of the multilanguage move has fully landed, so a rollback stays possible; the drop is its own
 * micro-migration afterwards. Nothing reads it and nothing writes it — see the migration
 * `2026_08_24_120000_example_language` for the reasoning and the Postgres column comment that
 * repeats it to anyone reading `\d+ term_examples`.
 */
final class TermExampleModel extends Model
{
    protected $table = 'term_examples';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];
}
