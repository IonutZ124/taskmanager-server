<?php

namespace Tests\Feature;

use App\Http\Controllers\BoardController;
use App\Models\Board;
use App\Models\BoardInvites;
use App\Models\Status;
use App\Models\User;
use Database\Factories\BoardInviteFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BoardTest extends TestCase
{
    use DatabaseTransactions;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_board_test()
    {
        $boardController = new BoardController();
        $request = Request::create('/create-task', 'POST', [
            'name' => 'asdf'
        ]);

        $user = User::factory()->create();
        Auth::shouldReceive('user')->once()->andReturn($user);

        $response = $boardController->add($request);

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function test_board_with_longer_name()
    {
        $boardController = new BoardController();
        $request = Request::create('/create-task', 'POST', [
            'name' => Str::random(51)
        ]);

        $response = $boardController->add($request);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function test_archive_board_test()
    {
        $boardController = new BoardController();
        $response = $boardController->archive(12321321);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_archive_board_member()
    {
        $boardController = new BoardController();
        $getRandomBoard = Board::inRandomOrder()->first();

        $user = User::factory()->create();
        Auth::shouldReceive('user')->once()->andReturn($user);

        $response = $boardController->archive($getRandomBoard->id);
        $this->assertEquals(405, $response->getStatusCode());
    }

    public function test_sendBoardInvite_user_not_allowed()
    {
        $boardController = new BoardController();
        $user = User::factory()->create();
        $getRandomBoard = Board::inRandomOrder()->first();
        Auth::shouldReceive('user')->once()->andReturn($user);

        $request = Request::create('/send-invite', 'POST', [
            "board_id" => $getRandomBoard->id,
            "email" => fake()->safeEmail()
        ]);

        $response = $boardController->sendInvite($request);

        $this->assertEquals(405, $response->getStatusCode());
    }

    public function test_sendBoardInvite_unexisting_board()
    {
        $boardController = new BoardController();
        $request = Request::create('/send-invite', 'POST', [
            "board_id" => "1909090",
            "email" => fake()->safeEmail()
        ]);

        $response = $boardController->sendInvite($request);
        $this->assertEquals(400, $response->getStatusCode());
    }
    public function test_sendBoardInvite_miss_email()
    {
        $boardController = new BoardController();
        $getRandomBoard = Board::inRandomOrder()->first();

        $request = Request::create('/send-invite', 'POST', [
            "board_id" => $getRandomBoard->id,
        ]);

        $response = $boardController->sendInvite($request);
        $this->assertEquals(400, $response->getStatusCode());
    }
    public function test_sendBoardInvite_allowed_user()
    {
        $boardController = new BoardController();

        $getRandomBoard = Board::inRandomOrder()->first();
        $boardOwnerID = $getRandomBoard->getOwner;
        $user = User::find($boardOwnerID->id);

        Auth::shouldReceive('user')->once()->andReturn($user);

        $request = Request::create('/send-invite', 'POST', [
            "board_id" => $getRandomBoard->id,
            "email" => fake()->safeEmail()
        ]);

        $response = $boardController->sendInvite($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_accept_board_invite_wrong_code()
    {
        $boardController = new BoardController();


        $request = Request::create('/accept-board-invite', 'POST');

        $response = $boardController->sendInvite($request);
        $this->assertEquals(400, $response->getStatusCode());

        $request = Request::create('/accept-board-invite', 'POST', [
            "code" => Str::random(5)
        ]);

        $response = $boardController->sendInvite($request);
        $this->assertEquals(400, $response->getStatusCode());
    }
    public function test_delete_board()
    {

        $boardController = new BoardController();
        $getRandomBoard = Board::inRandomOrder()->first();
        $getBoardOwner = $getRandomBoard->getOwner;

        $user = User::find($getRandomBoard->owner_id);
        Auth::shouldReceive('user')->once()->andReturn($user);

        $request = Request::create("/delete-board", 'DELETE');

        $response = $boardController->delete($getRandomBoard->id);

        $this->assertEquals(204, $response->getStatusCode());
    }

    public function test_delete_unexisting_board()
    {
        $boardController = new BoardController();

        $request = Request::create("/delete-board", 'DELETE');
        $response = $boardController->delete(99999);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_delete_random_board()
    {
        $boardController = new BoardController();
        $getRandomBoard = Board::inRandomOrder()->first();

        $user = User::factory()->create();
        Auth::shouldReceive('user')->once()->andReturn($user);

        $request = Request::create("/delete-board", 'DELETE');

        $response = $boardController->delete($getRandomBoard->id);

        $this->assertEquals(405, $response->getStatusCode());
    }

    public function test_get_board_where_user_is_member()
    {
        $boardController = new BoardController();
        $user = User::factory()->create();
        Auth::shouldReceive('user')->once()->andReturn($user);

        $request = Request::create("/get-joined-boards", 'GET');

        $response = $boardController->getBoardsWhereUserIsMember($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_get_board_content_owner_user()
    {
        $boardController = new BoardController();
        $getRandomBoard = Board::inRandomOrder()->first();
        $boardOwnerId = $getRandomBoard->owner_id;

        $user = User::find($boardOwnerId);
        Auth::shouldReceive('user')->once()->andReturn($user);

        $request = Request::create("/board", 'GET');
        $response = $boardController->getBoard($getRandomBoard->slug);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_get_board_content_random_user()
    {
        $boardController = new BoardController();
        $getRandomBoard = Board::inRandomOrder()->first();

        $user = User::factory()->create();
        Auth::shouldReceive('user')->once()->andReturn($user);

        $request = Request::create("/board", 'GET');
        $response = $boardController->getBoard($getRandomBoard->slug);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_get_board_content_super_admin()
    {
        $boardController = new BoardController();
        $getRandomBoard = Board::inRandomOrder()->first();

        $user = User::factory()->create();
        $user->isSuperAdmin = 1;
        Auth::shouldReceive('user')->once()->andReturn($user);

        $request = Request::create("/board", 'GET');
        $response = $boardController->getBoard($getRandomBoard->slug);
        $this->assertEquals(200, $response->getStatusCode());
    }
}