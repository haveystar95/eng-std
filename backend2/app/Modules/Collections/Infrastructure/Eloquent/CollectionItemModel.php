<?php

declare(strict_types=1);

namespace App\Modules\Collections\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $collection_id
 * @property string $term_id
 * @property int $position
 * @property string|null $note
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
final class CollectionItemModel extends Model
{
    use SoftDeletes;

    protected $table = 'collection_items';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = ['position' => 'int'];
}
