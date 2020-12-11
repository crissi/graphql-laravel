<?php

declare(strict_types=1);

use Faker\Generator as Faker;
use Rebing\GraphQL\Tests\Support\Models\Like;
use Rebing\GraphQL\Tests\Support\Models\Character;

/* @var Factory $factory */
$factory->define(Character::class, function (Faker $faker) {
    return [
        'type' => $faker->randomElement(['droid', 'human']),
        'hours_of_sleep_needed' => $faker->randomNumber(),
        'name' => $faker->name,
        'battery_left' => $faker->randomNumber(),
    ];
});
