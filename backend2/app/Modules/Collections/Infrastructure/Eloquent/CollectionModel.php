<?php

declare(strict_types=1);

namespace App\Modules\Collections\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string|null $owner_id
 * @property string $type
 * @property string $title
 * @property string|null $description
 * @property string|null $topic
 * @property string $source_lang
 * @property string $target_lang
 * @property string $visibility
 * @property string $source
 * @property bool $is_premium
 * @property bool $is_default
 * @property int $items_count
 * @property string|null $image_url
 * @property string|null $image_api_prompt
 * @property string|null $image_author
 * @property string|null $image_author_url
 * @property \Illuminate\Support\Carbon $created_at
 */
final class CollectionModel extends Model
{
    use SoftDeletes;

    protected $table = 'collections';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = ['items_count' => 'int', 'is_premium' => 'bool', 'is_default' => 'bool'];

    /** @return HasMany<CollectionItemModel, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CollectionItemModel::class, 'collection_id')->orderBy('position');
    }
}
