<?php

use Faker\Generator as Faker;

$factory->define(Baril\Sqlout\Tests\Models\Post::class, function (Faker $faker) {
    return [
        'title' => $faker->sentence(3),
        'body' => $faker->text(50),
    ];
});

$factory->define(Baril\Sqlout\Tests\Models\Comment::class, function (Faker $faker) {
    return [
        'author' => $faker->name(),
        'text' => $faker->text(50),
    ];
});
