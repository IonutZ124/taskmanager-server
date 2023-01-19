<?php

namespace Tests\Feature;

use App\Http\Controllers\StatusController;
use App\Models\Board;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;

class StatusTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    use DatabaseTransactions;

    public function test_create_status_owner()
    {
        $getRandomBoard = Board::inRandomOrder()->first();
        $board_owner = $getRandomBoard->owner_id;
        $statusController = new StatusController();
        $user = User::find($board_owner);

        Auth::shouldReceive('user')->once()->andReturn($user);

        $request = Request::create("/create-status", 'POST', [
            "name" => fake()->name() . "Status",
            "board_id" => $getRandomBoard->id
        ]);

        $response = $statusController->add($request);

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function test_create_status_not_allowed()
    {
        $getRandomBoard = Board::inRandomOrder()->first();
        $statusController = new StatusController();

        $user = User::factory()->create();
        Auth::shouldReceive('user')->once()->andReturn($user);

        $request = Request::create("/create-status", 'POST', [
            "name" => fake()->name() . "Status",
            "board_id" => $getRandomBoard->id
        ]);


        $response = $statusController->add($request);

        $this->assertEquals(405, $response->getStatusCode());
    }

    public function test_create_status_bad_request()
    {
        $getRandomBoard = Board::inRandomOrder()->first();
        $board_owner = $getRandomBoard->owner_id;
        $statusController = new StatusController();

        $request = Request::create("/create-status", 'POST', [
            "name" => fake()->name() . "Status",
        ]);

        $response = $statusController->add($request);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function test_create_status_longer_name()
    {
        $getRandomBoard = Board::inRandomOrder()->first();
        $board_owner = $getRandomBoard->owner_id;
        $statusController = new StatusController();

        $request = Request::create("/create-status", 'POST', [
            "name" => Str::random(51),
            "board_id" => $getRandomBoard->id
        ]);

        $response = $statusController->add($request);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function test_create_status_invalid_board_id()
    {
        $getRandomBoard = Board::inRandomOrder()->first();
        $board_owner = $getRandomBoard->owner_id;
        $statusController = new StatusController();

        $request = Request::create("/create-status", 'POST', [
            "name" => Str::random(51),
            "board_id" => 9999
        ]);

        $response = $statusController->add($request);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function test_get_all_statuses_for_board_allowed_user()
    {
        $getRandomBoard = Board::inRandomOrder()->first();
        $board_owner = $getRandomBoard->owner_id;
        $statusController = new StatusController();

        $user = User::find($board_owner);
        Auth::shouldReceive('user')->once()->andReturn($user);

        $request = Request::create("/get-statuses", 'GET');
        $response = $statusController->getAllStatusesForBoard($getRandomBoard->id);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_get_all_statuses_for_board_not_allowed()
    {
        $getRandomBoard = Board::inRandomOrder()->first();
        $board_owner = $getRandomBoard->owner_id;

        $statusController = new StatusController();
        $user = User::factory()->create();
        Auth::shouldReceive('user')->once()->andReturn($user);

        $request = Request::create("/get-statuses", 'GET');
        $response = $statusController->getAllStatusesForBoard($getRandomBoard->id);

        $this->assertEquals(405, $response->getStatusCode());
    }

    public function test_delete_status_allowed()
    {
        $getRandomStatus = Status::inRandomOrder()->first();
        $board_owner = $getRandomStatus->board->owner_id;
        $statusController = new StatusController();

        $user = User::find($board_owner);
        Auth::shouldReceive('user')->once()->andReturn($user);

        $request = Request::create("/delete-status", 'DELETE');
        $response = $statusController->delete($getRandomStatus->id);

        $this->assertEquals(204, $response->getStatusCode());
    }

    public function test_delete_status_not_allowed()
    {
        $getRandomStatus = Status::inRandomOrder()->first();
        $board_owner = $getRandomStatus->board->owner_id;
        $statusController = new StatusController();

        $user = User::factory()->create();
        Auth::shouldReceive('user')->once()->andReturn($user);

        $request = Request::create("/delete-status", 'DELETE');
        $response = $statusController->delete($getRandomStatus->id);

        $this->assertEquals(405, $response->getStatusCode());
    }
}