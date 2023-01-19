<?php

namespace Database\Factories;

use App\Models\BoardMembers;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TaskAssignedTo>
 */
class TaskAssignedToFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'task_id' => Task::inRandomOrder()->first()->id,
            'assigned_to' => BoardMembers::inRandomOrder()->first()->id,
            
        ];
    }
}
