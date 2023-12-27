<?php

namespace Baril\Sqlout\Tests\Factories;

use Baril\Sqlout\Tests\Models\Comment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition(): array
    {
        return [
            'author' => $this->faker->name(),
            'text' => $this->faker->text(50),
        ];
    }
}
