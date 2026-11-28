<?php

namespace ErnestDefoe\Fanfare\Listener;

use ErnestDefoe\CommunityGoals\Event\GoalReached;
use ErnestDefoe\Fanfare\Moment;
use ErnestDefoe\Fanfare\Moments;

/**
 * A Community Goal was reached (dispatched once, after its helpers were told
 * and its rewards handed out): every member who can see it gets the trophy
 * moment. Registered only while Community Goals is enabled.
 */
class CelebrateGoal
{
    public function __construct(protected Moments $moments)
    {
    }

    public function handle(GoalReached $event): void
    {
        $goal = $event->goal;

        $this->moments->record(Moment::GOAL, 'goal:'.$goal->id, $goal->title, $goal->reward ?: $goal->description, $goal->progress, $goal->tag_id);
    }
}
