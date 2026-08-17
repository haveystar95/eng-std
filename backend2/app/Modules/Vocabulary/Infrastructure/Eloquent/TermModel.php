<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $lang
 * @property string $text
 * @property string $normalized_text
 * @property string $type
 * @property string|null $pos
 * @property string|null $ipa
 * @property string|null $cefr
 * @property string|null $audio_url
 * @property string|null $image_url
 * @property string|null $image_api_prompt
 * @property string|null $image_author
 * @property string|null $image_author_url
 * @property string $source
 * @property string|null $created_by
 * @property \Illuminate\Support\Carbon $created_at
 */
final class TermModel extends Model
{
    protected $table = 'terms';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /** @return HasMany<TermTranslationModel, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(TermTranslationModel::class, 'term_id');
    }
}
