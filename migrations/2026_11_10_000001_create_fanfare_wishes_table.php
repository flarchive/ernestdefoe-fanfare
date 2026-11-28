<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * One member wishing another a happy day. The one-wish-per-pair-occasion-and-
 * year unique key is added by 2026_11_23_000001, which also files a wish under
 * what it congratulates (ref_id): installs from before 1.1 had an earlier key
 * here, which that migration finds by its columns and replaces.
 */
return Migration::createTable('fanfare_wishes', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('from_user_id');
    $table->unsignedInteger('to_user_id');
    $table->string('kind', 16);
    $table->unsignedSmallInteger('year');
    $table->dateTime('created_at');

    $table->index(['to_user_id', 'year']);
    $table->foreign('from_user_id')->references('id')->on('users')->cascadeOnDelete();
    $table->foreign('to_user_id')->references('id')->on('users')->cascadeOnDelete();
});
