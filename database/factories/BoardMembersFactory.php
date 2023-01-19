<?php

namespace Database\Factories;

use App\Models\Board;
use App\Models\BoardMembers;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BoardMembers>
 */
class BoardMembersFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'board_id' => Board::inRandomOrder()->first()->id,
            'user_id' => User::inRandomOrder()->first()->id,
            'role' => fake()->randomElement(["Admin", "Member"]),
            'isBoardOwner' => rand(0,1)
        ];
    }
}
