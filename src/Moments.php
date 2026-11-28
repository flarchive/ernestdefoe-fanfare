<?php

namespace ErnestDefoe\Fanfare;

use Carbon\Carbon;
use Flarum\Tags\Tag;
use Flarum\User\User;
use Illuminate\Contracts\Cache\Repository;

/**
 * The community's recent moments (milestones passed, Community Goals reached),
 * for members: one cached list, forgotten when a moment is recorded, so pages
 * cost nothing until there is something to celebrate.
 */
class Moments
{
    /** How long a moment stays on: it opens once by itself, then waits behind the header button. */
    public const DAYS = 3;

    protected const CACHE = 'ernestdefoe-fanfare.moments';

    public function __construct(protected Config $config, protected Repository $cache)
    {
    }

    /** Record a moment, once per ref, ever. */
    public function record(string $kind, string $ref, string $title, ?string $message, int $count, ?int $tagId = null): void
    {
        Moment::query()->insertOrIgnore([
            'kind' => $kind,
            'ref' => mb_substr($ref, 0, 80),
            'title' => mb_substr($title, 0, 200),
            'message' => $message !== null && $message !== '' ? mb_substr($message, 0, 2000) : null,
            'count' => max(0, $count),
            'tag_id' => $tagId,
            'created_at' => Carbon::now(),
        ]);

        $this->cache->forget(self::CACHE);
    }

    /**
     * The last few days' moments this member may see, newest first. Members only;
     * a reached goal needs Community Goals' view permission, and its tag.
     *
     * @return list<array{id: int, kind: string, title: string, message: ?string, count: int, at: string}>
     */
    public function forViewer(User $actor): array
    {
        if ($actor->isGuest() || ! $actor->hasPermission('viewForum')) {
            return [];
        }

        $rows = $this->recent();
        if (! $rows) {
            return [];
        }

        $goals = $this->config->goals() && $actor->hasPermission('communityGoals.view');
        $tagIds = array_values(array_unique(array_filter(array_column($rows, 'tag_id'))));
        $visibleTags = $tagIds && $this->config->tags() ? Tag::query()->whereVisibleTo($actor)->whereIn('id', $tagIds)->pluck('id')->map(fn ($id) => (int) $id)->all() : [];

        $out = [];
        foreach ($rows as $r) {
            if ($r['kind'] === Moment::GOAL && (! $goals || ($r['tag_id'] && ! in_array($r['tag_id'], $visibleTags, true)))) {
                continue;
            }
            // What a milestone counted (members, discussions, posts, tag), for the page's wording.
            $out[] = array_diff_key($r, ['tag_id' => 1, 'ref' => 1]) + ['what' => $r['kind'] === Moment::MILESTONE ? preg_replace('/^tag-\d+$/', 'tag', explode(':', $r['ref'])[1] ?? '') : null];
        }

        return $out;
    }

    /** @return list<array{id: int, kind: string, title: string, message: ?string, count: int, tag_id: ?int, ref: string, at: string}> */
    protected function recent(): array
    {
        return $this->cache->remember(self::CACHE, 900, fn () => Moment::query()
            ->where('created_at', '>=', Carbon::now()->subDays(self::DAYS))
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (Moment $m) => [
                'id' => $m->id,
                'kind' => $m->kind,
                'title' => $m->title,
                'message' => $m->message,
                'count' => $m->count,
                'tag_id' => $m->tag_id,
                'ref' => $m->ref,
                'at' => $m->created_at->toIso8601String(),
            ])
            ->all());
    }
}
