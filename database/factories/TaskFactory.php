<?php

namespace Database\Factories;

use App\Models\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => 'Task ' . strtoupper(fake()->randomLetter()) . fake()->randomLetter() . ' ' . fake()->randomNumber(2),
            'status_id' => Status::inRandomOrder()->first()->id,
            'isArchived' => rand(0,1),
            'isActive' => rand(0,1)
        ];
    }
}
