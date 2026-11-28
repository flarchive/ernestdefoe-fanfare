<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * The community's moments, celebrated for a few days: a milestone the forum
 * passed ("1,000 members") or a Community Goal reached. `ref` says which, so
 * each is celebrated once, ever.
 */
return Migration::createTable('fanfare_moments', function (Blueprint $table) {
    $table->increments('id');
    // milestone | goal
    $table->string('kind', 16);
    // milestone:members:1000, milestone:tag-3:500, goal:12
    $table->string('ref', 80)->unique();
    $table->string('title', 200);
    $table->text('message')->nullable();
    $table->unsignedInteger('count');
    // A goal counted in a tag: only members who can see the tag see the moment.
    $table->unsignedInteger('tag_id')->nullable();
    $table->dateTime('created_at')->index();
});
