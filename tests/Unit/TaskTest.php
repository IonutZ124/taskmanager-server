<?php

namespace Tests\Unit;

use App\Http\Controllers\TaskController;
use App\Models\BoardMembers;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;


class TaskTest extends TestCase
{
    use DatabaseTransactions;

    //use RefreshDatabase;

    /**
     * A basic unit test example.
     *
     * @return void
     */

    public function test_add_validator_name_only(): void
    {
        $taskController = new TaskController();

        $request = Request::create('/create-task', 'POST', [
            'name' => 'test',
        ]);

        $response = $taskController->add($request);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function test_add_validator_status_only()
    {
        $taskController = new TaskController();

        $request = Request::create('/create-task', 'POST', [
            'status_id' => '1',
        ]);

        $response = $taskController->add($request);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function test_add_validator_wrong_status()
    {
        $taskController = new TaskController();

        $request = Request::create('/create-task', 'POST', [
            'status_id' => '9999',
            'name' => 'test'
        ]);

        $response = $taskController->add($request);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function test_add_validator_wrong_type()
    {
        $taskController = new TaskController();

        $request = Request::create('/create-task', 'POST', [
            'status_id' => 'test',
            'name' => 'test'
        ]);

        $response = $taskController->add($request);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function test_add_user_is_admin()
    {
        $taskController = new TaskController();
        $request = Request::create('/create-task', 'POST', [
            'status_id' => '1',
            'name' => 'asdf'
        ]);

        $user = User::factory()->create();
        Auth::shouldReceive('user')->once()->andReturn($user);

        $response = $taskController->add($request);

        $this->assertEquals(405, $response->getStatusCode());
    }

    public function test_archive_task_not_found()
    {
        $taskController = new TaskController();

        $user = User::factory()->create();
        Auth::shouldReceive('user')->once()->andReturn($user);

        $task_id = 999999999;

        $response = $taskController->archive($task_id);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_archive_bad_id_type()
    {
        $taskController = new TaskController();

        $user = User::factory()->create();
        Auth::shouldReceive('user')->once()->andReturn($user);

        $task_id = 'asd';

        $response = $taskController->archive($task_id);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_archive_user_is_owner()
    {
        $taskController = new TaskController();

        $user = User::factory()->create();
        Auth::shouldReceive('user')->once()->andReturn($user);

        $task = Task::inRandomOrder()->first();

        $response = $taskController->archive($task->id);
        $this->assertEquals(405, $response->getStatusCode());
    }

    public function test_delete_task_not_found()
    {
        $taskController = new TaskController();

        $user = User::factory()->create();
        Auth::shouldReceive('user')->once()->andReturn($user);

        $task_id = 5555;

        $response = $taskController->delete($task_id);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_delete_bad_id_type()
    {
        $taskController = new TaskController();

        $user = User::factory()->create();
        Auth::shouldReceive('user')->once()->andReturn($user);

        $task_id = '5555';

        $response = $taskController->delete($task_id);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_delete_user_is_not_admin()
    {
        $taskController = new TaskController();

        $user = User::factory()->create();
        Auth::shouldReceive('user')->once()->andReturn($user);

        $task = Task::inRandomOrder()->first();

        Mockery::mock(BoardMembers::class)->shouldReceive('role')->andReturn('Member');

        $response = $taskController->delete($task->id);
        $this->assertEquals(405, $response->getStatusCode());
    }

    public function test_delete_user_is_not_member()
    {
        $taskController = new TaskController();

        $user = User::factory()->create();
        Auth::shouldReceive('user')->once()->andReturn($user);

        $task = Task::inRandomOrder()->first();
        Mockery::mock(BoardMembers::class)->shouldReceive('where')->andReturn(null);

        $response = $taskController->delete($task->id);
        $this->assertEquals(405, $response->getStatusCode());
    }

    public function test_change_status_validator_required()
    {
        $taskController = new TaskController();

        $request = Request::create('/change-task-status', 'POST', [
            'task_id' => ''
        ]);

        $response = $taskController->changeTaskStatus($request);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function test_change_status_is_user_assigned()
    {
        $taskController = new TaskController();

        $request = Request::create('/change-task-status', 'POST', [
            'task_id' => '2'
        ]);

        $user = User::factory()->create();
        Auth::shouldReceive('user')->andReturn($user);

        $task = Task::inRandomOrder()->first();
        Mockery::mock(BoardMembers::class)->shouldReceive('where')->andReturn(null);

        $response = $taskController->changeTaskStatus($request);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_get_task_history_not_found()
    {
        $taskController = new TaskController();

        $response = $taskController->getTaskHistory(9999);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_get_task_history_not_board_member()
    {
        $taskController = new TaskController();

        $task = Task::inRandomOrder()->first();
        $user = User::factory()->create();
        Auth::shouldReceive('user')->andReturn($user);

        $response = $taskController->getTaskHistory($task->id);
        $this->assertEquals(405, $response->getStatusCode());
    }

}
