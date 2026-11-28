<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * An admin's own look for an occasion: greeting, artwork, animation, effect
 * and sound, each falling back to Fanfare's own when left empty.
 */
return Migration::createTable('fanfare_looks', function (Blueprint $table) {
    $table->increments('id');
    // christmas, birthday, trophy, custom-<festoon id>…
    $table->string('occasion', 80)->unique();
    $table->string('title', 120)->nullable();
    $table->string('subtitle', 160)->nullable();
    // '' Fanfare's own | emoji | image | scene:<id> (another centrepiece)
    $table->string('art', 40)->default('');
    $table->string('emoji', 16)->nullable();
    $table->string('image_path', 100)->nullable();
    // rise | pop | float | none
    $table->string('animation', 8)->default('rise');
    // '' the occasion's own | none | confetti | snow | fireworks | lanterns | leaves
    $table->string('effect', 16)->default('');
    // '' the occasion's own | none | a built-in tune | upload
    $table->string('sound', 16)->default('');
    $table->string('sound_path', 100)->nullable();
    $table->dateTime('updated_at')->nullable();
});
