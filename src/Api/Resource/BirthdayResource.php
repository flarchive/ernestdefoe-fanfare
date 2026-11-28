<?php

namespace ErnestDefoe\Fanfare\Api\Resource;

use ErnestDefoe\Fanfare\Birthday;
use ErnestDefoe\Fanfare\Config;
use ErnestDefoe\Fanfare\People;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\User\User;
use Tobyz\JsonApiServer\Context as OriginalContext;

/**
 * A member's birthday (type `fanfare-birthdays`, id = the user's id), read and
 * written only by Settings → Fanfare: by the member, or by moderators who can
 * edit them. Nothing else loads it, so ordinary pages carry no birthday query.
 * A member who never set one gets the defaults; their first save makes the row.
 *
 * With datlechin/flarum-birthdays installed its fields are used instead and
 * this resource refuses every request.
 *
 * @extends AbstractDatabaseResource<Birthday>
 */
class BirthdayResource extends AbstractDatabaseResource
{
    public function __construct(protected Config $config, protected People $people)
    {
    }

    public function type(): string
    {
        return 'fanfare-birthdays';
    }

    public function model(): string
    {
        return Birthday::class;
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Show::make()->authenticated(),
            Endpoint\Update::make()->authenticated(),
        ];
    }

    /** The member's own row, or a fresh one with the defaults; null (404) for anyone else. */
    public function find(string $id, OriginalContext $context): ?object
    {
        $actor = $context->getActor();
        $user = User::query()->find((int) $id);

        if ($this->config->datlechin() || ! $user || ($actor->id !== $user->id && ! $actor->can('edit', $user))) {
            return null;
        }

        return Birthday::query()->find($user->id) ?? tap(new Birthday(), function (Birthday $row) use ($user) {
            $row->user_id = $user->id;
            $row->visibility = Birthday::DAY;
        });
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('md')
                ->nullable()
                ->writable()
                ->regex('/^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/')
                ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail) {
                    if (is_string($value) && ! checkdate((int) substr($value, 0, 2), (int) substr($value, 3, 2), 2000)) {
                        $fail('validation.date');
                    }
                }),
            Schema\Integer::make('year')
                ->nullable()
                ->writable()
                ->min(1900)
                ->max((int) date('Y')),
            Schema\Integer::make('visibility')
                ->writable()
                ->min(Birthday::HIDDEN)
                ->max(Birthday::DAY_AND_AGE),
        ];
    }

    /** @param Birthday $model */
    public function saved(object $model, OriginalContext $context): ?object
    {
        // The day's lists and the member's own greeting are cached.
        $this->people->forget();

        return $model;
    }
}
