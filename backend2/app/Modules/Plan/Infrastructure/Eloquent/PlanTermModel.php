<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $scene_id
 * @property string $user_id
 * @property string $kind
 * @property string $ref
 * @property int $position
 * @property string $text_target
 * @property string $text_native
 * @property string|null $pronunciation_native
 * @property string|null $definition_target
 * @property string|null $example_target
 * @property string|null $example_native
 * @property string|null $speaking_key
 * @property list<string>|null $simplified_variants
 * @property string|null $image_prompt
 * @property string|null $image_url
 * @property string|null $image_author
 * @property string|null $image_author_url
 * @property string|null $image_tone the photo's tone, or — with no photo — the tone the ladder painted the card with
 */
final class PlanTermModel extends Model
{
    protected $table = 'plan_terms';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = ['simplified_variants' => 'array', 'position' => 'int'];
}
