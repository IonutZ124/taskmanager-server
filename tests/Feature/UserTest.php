<?php

namespace Tests\Feature;

use App\Http\Controllers\UserController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserTest extends TestCase
{
    use DatabaseTransactions;
    /**
     * A basic feature test example.
     *
     * @return void
     */

    public function test_register_user_succes()
    {
        $userController = new UserController();
        $name = fake()->name();
        $email = fake()->email();
        $password = "password";

        $request = Request::create("/register", "POST", [
            "name" => $name,
            "email" => $email,
            "password" => $password,
            "password_confirmation" => $password
        ]);

        $response = $userController->register($request);
        $this->assertEquals(201, $response->getStatusCode());
    }

    public function test_register_confirm_password_wrong()
    {
        $userController = new UserController();
        $name = fake()->name();
        $email = fake()->email();
        $password = "password";

        $request = Request::create("/register", "POST", [
            "name" => $name,
            "email" => $email,
            "password" => $password,
            "password_confirmation" => "abc"
        ]);

        $response = $userController->register($request);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function test_register_miss_field()
    {
        $userController = new UserController();
        $name = fake()->name();
        $email = fake()->email();
        $password = "password";

        $request = Request::create("/register", "POST", [
            "name" => $name,
            "password" => $password,
            "password_confirmation" => "abc"
        ]);

        $response = $userController->register($request);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function test_login_wrong_credentials()
    {
        $userController = new UserController();

        $request = Request::create("/login", "POST", [
            "email" => fake()->safeEmail(),
            "password" => "abc"
        ]);

        $response = $userController->login($request);
        $this->assertEquals(400, $response->getStatusCode());
    }
    public function test_getUser()
    {
        $userController = new UserController();

        $user = User::inRandomOrder()->first();

        Auth::shouldReceive('user')->once()->andReturn($user);
        $request = Request::create("/user", "GET");

        $response = $userController->getUser($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_getUser_boards()
    {
        $getRandomUser = User::inRandomOrder()->first();
        $userController = new UserController();

        Auth::shouldReceive("user")->once()->andReturn($getRandomUser);

        $request = Request::create("/get-user-boards", "GET");

        $response = $userController->getUserBoards();
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_getUser_archived_boards()
    {
        $getRandomUser = User::inRandomOrder()->first();
        $userController = new UserController();

        Auth::shouldReceive("user")->once()->andReturn($getRandomUser);

        $request = Request::create("/get-user-archived-boards", "GET");

        $response = $userController->getUserArchivedBoards();
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_getUser_archived_tasks()
    {
        $getRandomUser = User::inRandomOrder()->first();
        $userController = new UserController();

        Auth::shouldReceive("user")->once()->andReturn($getRandomUser);

        $request = Request::create("/get-user-archived-tasks", "GET");

        $response = $userController->getUserArchivedTasks();
        $this->assertEquals(200, $response->getStatusCode());
    }
}