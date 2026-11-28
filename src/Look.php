<?php

namespace ErnestDefoe\Fanfare;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;

/**
 * An admin's own look for one occasion. Empty fields keep Fanfare's own.
 *
 * @property int $id
 * @property string $occasion
 * @property string|null $title
 * @property string|null $subtitle
 * @property string $art
 * @property string|null $emoji
 * @property string|null $image_path
 * @property string $animation
 * @property string $effect
 * @property string $sound
 * @property string|null $sound_path
 * @property Carbon|null $updated_at
 */
class Look extends AbstractModel
{
    public const ANIMATIONS = ['rise', 'pop', 'float', 'none'];
    public const EFFECTS = ['', 'none', 'confetti', 'snow', 'fireworks', 'lanterns', 'leaves'];
    public const TUNES = ['birthday', 'christmas', 'auld', 'chime', 'fanfare', 'spooky'];
    public const SOUNDS = ['', 'none', 'upload', ...self::TUNES];

    /** The moments a look can be for, beside the holidays and Festoon days. */
    public const PEOPLE = ['birthday', 'anniversary', 'forum', 'course', 'trophy'];

    protected $table = 'fanfare_looks';

    public $timestamps = true;

    public const CREATED_AT = null;

    protected $casts = ['updated_at' => 'datetime'];
}
