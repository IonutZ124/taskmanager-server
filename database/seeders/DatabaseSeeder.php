<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        \App\Models\User::factory(5)->create();
        // \App\Models\Board::factory(100)->create();
        // \App\Models\Status::factory(100)->create();
        // \App\Models\BoardMembers::factory(100)->create();
        // \App\Models\Task::factory(300)->create();
        // \App\Models\TaskAssignedTo::factory(400)->create();
        // \App\Models\TaskComment::factory(150)->create();
    }
}
