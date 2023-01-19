<?php

namespace Tests\Unit;

use App\Http\Controllers\TaskCommentController;
use App\Models\BoardMembers;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

class CommentsTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * A basic unit test example.
     *
     * @return void
     */
    public function test_add_comment_validator_fails(): void
    {
        $taskCommentController = new TaskCommentController();

        $request = Request::create('/create-comment', 'POST', [
            'comment' => '',
            'task_id' => '',
            'tagged_user_email' => ''
        ]);

        $response = $taskCommentController->add($request);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function test_add_comment_user_is_not_member(): void
    {
        $taskCommentController = new TaskCommentController();

        $request = Request::create('/create-comment', 'POST', [
            'comment' => 'aaa',
            'task_id' => 1
        ]);

        $user = User::factory()->create();
        Auth::shouldReceive('user')->andReturn($user);

        $response = $taskCommentController->add($request);

        $this->assertEquals(405, $response->getStatusCode());
    }

    public function test_add_comment_tag_yourself(): void
    {
        $taskCommentController = new TaskCommentController();

        $user = User::find(1);

        $request = Request::create('/create-comment', 'POST', [
            'comment' => 'aaa',
            'task_id' => 1,
            'tagged_user_email' => $user->email
        ]);


        Auth::shouldReceive('user')->andReturn($user);
        Mockery::mock(BoardMembers::class)->shouldReceive('where')->andReturn(false);

        $response = $taskCommentController->add($request);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function test_get_comments_wrong_task_id(): void
    {
        $taskCommentController = new TaskCommentController();

        $response = $taskCommentController->get(999999);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_get_comments_wrong_input(): void
    {
        $taskCommentController = new TaskCommentController();

        $response = $taskCommentController->get('1');

        $this->assertEquals(500, $response->getStatusCode());
    }

    public function test_get_comments_not_board_member(): void
    {
        $taskCommentController = new TaskCommentController();
        $task = Task::inRandomOrder()->first();
        $user = User::factory()->create();
        Auth::shouldReceive('user')->andReturn($user);

        $response = $taskCommentController->get($task->id);

        $this->assertEquals(405, $response->getStatusCode());
    }

    public function test_delete_comment_wrong_id(): void
    {
        $taskCommentController = new TaskCommentController();

        $response = $taskCommentController->delete(5555555);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_delete_comment_bad_input(): void
    {
        $taskCommentController = new TaskCommentController();

        $response = $taskCommentController->delete('1');

        $this->assertEquals(500, $response->getStatusCode());
    }

    public function test_delete_comment_not_board_member(): void
    {
        $taskCommentController = new TaskCommentController();
        $comment = TaskComment::inRandomOrder()->first();
        $user = User::factory()->create();
        Auth::shouldReceive('user')->andReturn($user);

        $response = $taskCommentController->delete($comment->id);

        $this->assertEquals(401, $response->getStatusCode());
    }
}
