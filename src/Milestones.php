<?php

namespace ErnestDefoe\Fanfare;

use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Tags\Tag;
use Flarum\User\Guest;
use Flarum\User\User;

/**
 * All-time milestones the admin sets ("1,000 members", "500 discussions in
 * Tutorials"): checked hourly by fanfare:greet, each celebrated once, ever.
 *
 * Only what anyone can see is counted. A milestone fires when the count crosses
 * it after it was set: Fanfare remembers the last count it saw per metric, so a
 * milestone the forum passed long ago never fires out of the blue.
 */
class Milestones
{
    public const METRICS = ['members', 'discussions', 'posts', 'tag'];

    protected const SEEN = 'ernestdefoe-fanfare.milestones_seen';

    public function __construct(
        protected Config $config,
        protected Moments $moments,
        protected SettingsRepositoryInterface $settings,
    ) {
    }

    /** @return int how many milestones were celebrated now */
    public function check(): int
    {
        $list = $this->config->communityMilestones();
        if (! $list) {
            return 0;
        }

        $seen = json_decode((string) $this->settings->get(self::SEEN), true) ?: [];
        $counts = [];
        $fired = 0;

        foreach ($list as $m) {
            $key = $m['metric'] === 'tag' ? 'tag-'.$m['tag'] : $m['metric'];
            $count = $counts[$key] ??= $this->count($m['metric'], $m['tag']);
            if ($count === null) {
                continue;
            }

            // Crossed since we last looked (and we had looked before).
            if (isset($seen[$key]) && $seen[$key] < $m['at'] && $count >= $m['at']) {
                // No title: the page words it ("1,000 members!"); for a tag, its name goes in.
                $title = $m['title'] !== '' ? $m['title'] : ($m['metric'] === 'tag' ? $this->tagName($m['tag']) : '');
                $this->moments->record(Moment::MILESTONE, "milestone:$key:{$m['at']}", $title, $m['message'], $m['at']);
                $fired++;
            }
        }

        $before = $seen;
        foreach ($counts as $key => $count) {
            if ($count !== null) {
                $seen[$key] = $count;
            }
        }
        // Written only when a count moved: a quiet forum doesn't rewrite settings every hour.
        if ($seen !== $before) {
            $this->settings->set(self::SEEN, json_encode($seen));
        }

        return $fired;
    }

    protected function tagName(?int $tag): string
    {
        return $tag && $this->config->tags() ? (string) Tag::query()->whereKey($tag)->value('name') : '';
    }

    /** What anyone can see: confirmed members, and discussions and posts a guest could read. Null when the tag metric has no Tags. */
    public function count(string $metric, ?int $tag = null): ?int
    {
        $guest = new Guest();

        return match ($metric) {
            'members' => User::query()->where('is_email_confirmed', true)->count(),
            'discussions' => Discussion::query()->whereVisibleTo($guest)->count(),
            'posts' => Post::query()->whereVisibleTo($guest)->where('type', 'comment')->count(),
            'tag' => $this->config->tags() && $tag
                ? Discussion::query()->whereVisibleTo($guest)->whereHas('tags', fn ($q) => $q->whereKey($tag))->count()
                : null,
            default => null,
        };
    }
}
