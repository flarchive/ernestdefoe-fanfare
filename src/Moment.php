<?php

namespace ErnestDefoe\Fanfare;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;

/**
 * A moment for the whole community: a milestone the forum passed, or a
 * Community Goal reached. Celebrated for a few days, once per member.
 *
 * @property int $id
 * @property string $kind milestone | goal
 * @property string $ref
 * @property string $title
 * @property string|null $message
 * @property int $count
 * @property int|null $tag_id
 * @property Carbon $created_at
 */
class Moment extends AbstractModel
{
    public const MILESTONE = 'milestone';
    public const GOAL = 'goal';

    protected $table = 'fanfare_moments';

    public $timestamps = false;

    protected $casts = ['created_at' => 'datetime', 'count' => 'integer', 'tag_id' => 'integer'];
}
