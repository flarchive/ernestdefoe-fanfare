<?php

namespace ErnestDefoe\Fanfare\Tests\integration\api;

use ErnestDefoe\CommunityGoals\Event\GoalReached;
use ErnestDefoe\CommunityGoals\Goal;
use ErnestDefoe\Fanfare\Config;
use ErnestDefoe\Fanfare\Milestones;
use ErnestDefoe\Fanfare\Occasions;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\Attributes\Test;

/**
 * The community's moments: all-time milestones (fanfare:greet's hourly check)
 * and Community Goals reached (its GoalReached event).
 */
class MomentsTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $goals = str_contains($this->name(), 'goal');
        $this->extension(...[...($goals ? ['ernestdefoe-community-goals'] : []), 'ernestdefoe-fanfare']);
        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'mira', 'email' => 'mira@machine.local', 'is_email_confirmed' => 1],
            ],
            'group_user' => [['user_id' => 3, 'group_id' => 3]],
        ]);
        $this->setting(Config::OCCASIONS, json_encode(array_map(fn () => ['on' => false], Occasions::CATALOGUE)));
    }

    protected function set(string $key, mixed $value): void
    {
        $this->app()->getContainer()->make(SettingsRepositoryInterface::class)->set($key, $value);
    }

    /** The fanfare payload's moments for a viewer. */
    protected function moments(?int $as): array
    {
        $html = $this->send($this->request('GET', '/', $as ? ['authenticatedAs' => $as] : []))->getBody()->getContents();
        if (! preg_match('/"moments":(\[.*?\])[,}]/', $html, $m)) {
            return [];
        }

        return json_decode($m[1], true) ?? [];
    }

    protected function check(): int
    {
        return $this->app()->getContainer()->make(Milestones::class)->check();
    }

    #[Test]
    public function a_milestone_fires_once_when_crossed_and_never_for_one_already_passed()
    {
        $this->app();
        $members = User::query()->where('is_email_confirmed', true)->count();
        $this->set(Config::COMMUNITY_MILESTONES, json_encode([
            ['metric' => 'members', 'at' => $members, 'title' => 'Long ago'],
            ['metric' => 'members', 'at' => $members + 1, 'message' => 'Thank you all!'],
        ]));

        $this->assertSame(0, $this->check(), 'the first look only notes where the forum is');

        $this->database()->table('users')->insert(['id' => 4, 'username' => 'newbie', 'email' => 'newbie@machine.local', 'is_email_confirmed' => 1, 'password' => 'x', 'joined_at' => \Carbon\Carbon::now()]);
        $this->assertSame(1, $this->check(), 'crossed since the last look');
        $this->assertSame(0, $this->check(), 'once, ever');

        $seen = $this->moments(3);
        $this->assertCount(1, $seen);
        $this->assertSame(['kind' => 'milestone', 'title' => '', 'message' => 'Thank you all!', 'count' => $members + 1, 'what' => 'members'], array_intersect_key($seen[0], array_flip(['kind', 'title', 'message', 'count', 'what'])));
        $this->assertSame([], $this->moments(null), 'members only');
    }

    #[Test]
    public function a_reached_goal_is_a_moment_for_members_who_can_see_goals()
    {
        $this->app();
        $goal = new Goal();
        $goal->title = 'Our first 500 replies';
        $goal->metric = 'replies';
        $goal->target = 500;
        $goal->progress = 512;
        $goal->reward = 'A party theme for a week';
        $goal->starts_at = \Carbon\Carbon::now()->subWeek();
        $goal->ends_at = \Carbon\Carbon::now()->addWeek();
        $goal->save();

        $this->app()->getContainer()->make(Dispatcher::class)->dispatch(new GoalReached($goal));
        $this->app()->getContainer()->make(Dispatcher::class)->dispatch(new GoalReached($goal));

        $seen = $this->moments(3);
        $this->assertCount(1, $seen, 'once per goal');
        $this->assertSame(['kind' => 'goal', 'title' => 'Our first 500 replies', 'message' => 'A party theme for a week', 'count' => 512], array_intersect_key($seen[0], array_flip(['kind', 'title', 'message', 'count'])));

        $this->database()->table('group_permission')->where('permission', 'communityGoals.view')->delete();
        $this->app()->getContainer()->make(\Flarum\Group\PermissionCache::class)->flush();
        $this->assertSame([], $this->moments(3), 'not without the goals view permission');
    }
}
