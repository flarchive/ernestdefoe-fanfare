<?php

namespace ErnestDefoe\Fanfare\Content;

use ErnestDefoe\Fanfare\Config;
use ErnestDefoe\Fanfare\Milestones;
use ErnestDefoe\Fanfare\Occasions;
use Flarum\Frontend\Document;
use Illuminate\Contracts\Cache\Repository;
use Flarum\Tags\Tag;

/** The admin page's live numbers: when each occasion falls next (Festoon's own days too), and what is installed beside Fanfare. */
class AdminPayload
{
    public function __construct(protected Config $config, protected Occasions $occasions, protected Milestones $milestones, protected Repository $cache)
    {
    }

    /**
     * Where the forum is now, for picking milestones ahead of it. Cached for an
     * hour: the admin frontend loads on every admin page, not only Fanfare's.
     *
     * @return array<string, int|null>
     */
    protected function counts(): array
    {
        return $this->cache->remember('ernestdefoe-fanfare.admin-counts', 3600, fn () => [
            'members' => $this->milestones->count('members'),
            'discussions' => $this->milestones->count('discussions'),
            'posts' => $this->milestones->count('posts'),
        ]);
    }

    public function __invoke(Document $document): void
    {
        $document->payload['fanfareAdmin'] = [
            'next' => $this->occasions->next(),
            'catalogue' => array_map(fn (array $o) => ['on' => $o['on'], 'shift' => (bool) ($o['shift'] ?? false), 'festoon' => isset($o['festoon'])], Occasions::CATALOGUE),
            'festoon' => $this->config->festoon(),
            'custom' => array_map(fn (array $d) => ['name' => $d['name'], 'emoji' => $d['emoji']], $this->config->festoonDays()),
            'syllabus' => $this->config->syllabus(),
            // Milestones: where the forum is now, so admins pick numbers ahead of it, and the tags to count in.
            'counts' => $this->counts(),
            'tags' => $this->config->tags() ? Tag::query()->orderBy('name')->limit(200)->pluck('name', 'id')->all() : [],
            'goals' => $this->config->goals(),
            'datlechin' => $this->config->datlechin(),
            'timezone' => $this->config->timezone()->getName(),
        ];
    }
}
