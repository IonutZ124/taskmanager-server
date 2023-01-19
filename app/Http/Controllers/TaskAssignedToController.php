<?php

namespace App\Http\Controllers;

use App\Events\SendEventToClient;
use App\Models\Board;
use App\Models\BoardMembers;
use App\Models\Task;
use App\Models\TaskAssignedTo;
use App\Models\TaskHistory;
use App\Models\User;
use App\Models\UserNotifications;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TaskAssignedToController extends ApiController
{
    public function assignTaskToUser(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "task_id" => "required|exists:tasks,id",
                "board_id" => "required",
                "email" => "required|exists:users,email"
            ]);

            if ($validator->fails()) {
                return $this->sendError($validator->messages()->toArray());
            }

            $user = User::where("email", $request->get("email"))->first();
            $authUser = Auth::user();

            if (!BoardMembers::where("user_id", $authUser->id)->where("board_id", $request->get("board_id"))->first()) {
                return $this->sendError("Not allowed to perform this action", [], Response::HTTP_METHOD_NOT_ALLOWED);
            }

            if (!BoardMembers::where("user_id", $user->id)->where("board_id", $request->get("board_id"))->first()) {
                return $this->sendError("This user is not a board member", []);
            }

            if (TaskAssignedTo::where("task_id", $request->get("task_id"))->where("assigned_to", $user->id)->first()) {
                return $this->sendError("This user is already assigned", []);
            }

            $board = Board::where("id", $request->get("board_id"))->first();
            $task = Task::where("id", $request->get("task_id"))->first();

            $assignUser = new TaskAssignedTo();
            $assignUser->task_id = $request->get("task_id");
            $assignUser->assigned_to = $user->id;
            $assignUser->save();

            $userNotification = new UserNotifications();
            $userNotification->user_id = $user->id;
            $userNotification->message = "You have been assigned to a new task, board: {$board->name}, task: {$task->name}";
            $userNotification->save();
            event(new SendEventToClient($userNotification, [$userNotification->user_id], "notification"));

            $taskHistory = new TaskHistory();
            $taskHistory->task_id = $task->id;
            $taskHistory->user_id = $user->id;
            if ($user->email === $authUser->email) {
                $taskHistory->action = "{$user->email} assigned himself task {$task->name}";
            } else {
                $taskHistory->action = "{$user->email} has been assigned to task {$task->name} by {$authUser->email}";
            }
            $taskHistory->save();

            return $this->sendResponse([], 201);
        } catch (Exception $exception) {
            Log::error($exception);
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getAssignedUsers($task_id)
    {
        try {
            $authUser = Auth::user();
            $task = Task::find($task_id);

            if (!$task) {
                return $this->sendError("Task not found", [], Response::HTTP_NOT_FOUND);
            }

            if (!$authUser->isSuperAdmin) {
                $isUserBoardMember = BoardMembers::where("user_id", $authUser->id)->where("board_id", $task->status->board->id)->first();

                if (!$isUserBoardMember) {
                    return $this->sendError("Not allowed to perform this action", [], Response::HTTP_METHOD_NOT_ALLOWED);
                }
            }

            $isCurrentUserAssigned = TaskAssignedTo::where("assigned_to", $authUser->id)->where("task_id", $task_id)->first();
            $assignedUsers = TaskAssignedTo::query()->where("task_id", $task_id)->paginate(30);

            $result = [
                "users" => [],
                "currentPage" => $assignedUsers->currentPage(),
                "hasMorePages" => $assignedUsers->hasMorePages(),
                "lastPage" => $assignedUsers->lastPage(),
                "isCurrentUserAssigned" => $isCurrentUserAssigned ? true : false
            ];

            foreach ($assignedUsers->items() as $assigned) {
                $assignedUser = $assigned->getUser;
                $result["users"][] = $assignedUser;
            }

            return $this->sendResponse($result);
        } catch (Exception $exception) {
            error_log($exception);
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
