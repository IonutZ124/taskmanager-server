<?php

namespace App\Http\Controllers;

use App\Events\SendEventToClient;
use App\Models\BoardMembers;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskHistory;
use App\Models\User;
use App\Models\UserNotifications;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TaskCommentController extends ApiController
{
    public function add(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $validate = Validator::make($request->all(), [
                'comment' => 'required|max:200',
                'task_id' => 'required|exists:tasks,id',
                'tagged_user_email' => 'nullable'
            ]);

            if ($validate->fails()) {
                return $this->sendError("Bad request", $validate->messages()->toArray());
            }

            $authUser = Auth::user();

            $task = Task::find($request->get('task_id'));

            if (!$task) {
                return $this->sendError('Task not found!', [], Response::HTTP_NOT_FOUND);
            }

            if (!BoardMembers::where("user_id", $authUser->id)->where("board_id", $task->status->board->id)->first()) {
                return $this->sendError("Not allowed to perform this action", [], Response::HTTP_METHOD_NOT_ALLOWED);
            }

            $tagged_user = User::where("email", $request->get("tagged_user_email"))->first();
            if ($tagged_user && $authUser->id == $tagged_user->id) {
                return $this->sendError("You cannot tag yourself", []);
            }
            $taskComment = new TaskComment();
            $taskComment->comment = $request->get('comment');
            $taskComment->task_id = $request->get('task_id');
            $taskComment->user_email = $authUser->email;
            $taskComment->save();


            if ($tagged_user) {
                if (!BoardMembers::where('board_id', $task->status->board->id)->where('user_id', $tagged_user->id)->first()) {
                    return $this->sendError("Tagged user is not member of this board!", []);
                }

                $userNotification = new UserNotifications();
                $userNotification->user_id = $tagged_user->id;
                $userNotification->message = "{$authUser->name} has mentioned you in a comment, board: {$task->status->board->name}, status: {$task->status->name} task: {$task->name}";
                $userNotification->save();
                event(new SendEventToClient($userNotification, [$userNotification->user_id], "notification"));
            }
            $getAllBoardMemembers = BoardMembers::where("board_id", $task->status->board->id)->get();
            $users = [];

            foreach ($getAllBoardMemembers as $member) {
                $users[] = $member->user_id;
            }

            event(new SendEventToClient($taskComment, $users, "comments"));

            return $this->sendResponse($taskComment->toArray(), Response::HTTP_CREATED);
        } catch (Exception $exception) {
            //dd($exception);
            Log::error($exception);

            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function get($task_id)
    {
        try {
            $task = Task::find($task_id);

            if (!$task) {
                return $this->sendError('Task not found!', [], Response::HTTP_NOT_FOUND);
            }

            $authUser = Auth::user();
            $isUserBoardMember = BoardMembers::where("user_id", $authUser->id)->where("board_id", $task->status->board->id)->first();

            if (!$isUserBoardMember) {
                return $this->sendError("Not allowed to perform this action", [], Response::HTTP_METHOD_NOT_ALLOWED);
            }

            $comments = TaskComment::query();
            $getComments = $comments->where("task_id", $task_id)->orderBy("created_at", "DESC")->paginate(5);

            $result = [
                "comments" => $getComments->items(),
                "currentPage" => $getComments->currentPage(),
                "hasMorePages" => $getComments->hasMorePages(),
                "lastPage" => $getComments->lastPage()
            ];

            return $this->sendResponse($result);
        } catch (Exception $exception) {
            Log::error($exception);
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function delete($comment_id)
    {
        try {
            $comment = TaskComment::find($comment_id);

            if (!$comment) {
                return $this->sendError('Comment not found!', [], Response::HTTP_NOT_FOUND);
            }

            $user = Auth::user();
            $board = Task::find($comment->task_id)->status->board;

            if (!BoardMembers::where("user_id", $user->id)->where("board_id", $board->id)->first()) {
                return $this->sendError("Not allowed", [], Response::HTTP_UNAUTHORIZED);
            }

            $getAllBoardMemembers = BoardMembers::where("board_id", $board->id)->get();
            $users = [];

            foreach ($getAllBoardMemembers as $member) {
                $users[] = $member->user_id;
            }

            event(new SendEventToClient(["comment_id" => $comment_id, "task_id" => $comment->task_id], $users, "delete_comment"));

            DB::beginTransaction();
            $comment->delete();
            DB::commit();

            $taskHistory = new TaskHistory();
            $taskHistory->task_id = $comment->task_id;
            $taskHistory->user_id = $user->id;
            if ($user->email === $comment->user_email) {
                $taskHistory->action = "{$user->email}" . " deleted own comment";
            } else {
                $taskHistory->action = "{$user->email}" . " deleted {$comment->user_email}`s comment";
            }
            $taskHistory->save();

            return $this->sendResponse([], Response::HTTP_NO_CONTENT);
        } catch (Exception $exception) {
            Log::error($exception);
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}