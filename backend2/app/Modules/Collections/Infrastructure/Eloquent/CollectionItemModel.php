<?php

declare(strict_types=1);

namespace App\Modules\Collections\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $collection_id
 * @property string $term_id
 * @property int $position
 * @property string|null $note
 */
final class CollectionItemModel extends Model
{
    protected $table = 'collection_items';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = ['position' => 'int'];
}
