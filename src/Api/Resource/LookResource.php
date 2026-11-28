<?php

namespace ErnestDefoe\Fanfare\Api\Resource;

use ErnestDefoe\Fanfare\Look;
use ErnestDefoe\Fanfare\Looks;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Tobyz\JsonApiServer\Context as OriginalContext;

/**
 * The admin's looks (type `fanfare-looks`): one per occasion, made, changed
 * and removed from Administration → Fanfare. Admins only. Pictures and sounds
 * are uploaded separately (Api\UploadLookFileController); what the page shows
 * goes out through Looks, today's only.
 *
 * @extends AbstractDatabaseResource<Look>
 */
class LookResource extends AbstractDatabaseResource
{
    public function __construct(protected Looks $looks)
    {
    }

    public function type(): string
    {
        return 'fanfare-looks';
    }

    public function model(): string
    {
        return Look::class;
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()->admin(),
            Endpoint\Create::make()->admin(),
            Endpoint\Update::make()->admin(),
            Endpoint\Delete::make()->admin(),
        ];
    }

    public function fields(): array
    {
        $occasions = $this->looks->occasions();

        return [
            Schema\Str::make('occasion')
                ->requiredOnCreate()
                ->writableOnCreate()
                ->in($occasions)
                ->unique('fanfare_looks', 'occasion'),
            Schema\Str::make('title')->nullable()->writable()->maxLength(120),
            Schema\Str::make('subtitle')->nullable()->writable()->maxLength(160),
            // Fanfare's own, an emoji, an uploaded picture, or another of Fanfare's centrepieces.
            Schema\Str::make('art')
                ->writable()
                ->in(['', 'emoji', 'image', ...array_map(fn ($id) => 'scene:'.$id, array_values(array_filter($occasions, fn ($id) => ! str_starts_with($id, 'custom-'))))]),
            Schema\Str::make('emoji')->nullable()->writable()->maxLength(16),
            Schema\Str::make('animation')->writable()->in(Look::ANIMATIONS),
            Schema\Str::make('effect')->writable()->in(Look::EFFECTS),
            Schema\Str::make('sound')->writable()->in(Look::SOUNDS),
            Schema\Str::make('imageUrl')->nullable()->get(fn (Look $l) => $l->image_path ? $this->looks->url($l->image_path) : null),
            Schema\Str::make('soundUrl')->nullable()->get(fn (Look $l) => $l->sound_path ? $this->looks->url($l->sound_path) : null),
        ];
    }

    /** @param Look $model */
    public function saved(object $model, OriginalContext $context): ?object
    {
        $this->looks->forget();

        return $model;
    }

    /** @param Look $model */
    public function deleted(object $model, OriginalContext $context): void
    {
        $this->looks->delete($model->image_path);
        $this->looks->delete($model->sound_path);
        $this->looks->forget();
    }
}
