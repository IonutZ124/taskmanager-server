<?php

namespace App\Http\Controllers;

use App\Events\SendEventToClient;
use App\Models\ArchivedTasks;
use App\Models\BoardMembers;
use App\Models\Status;
use App\Models\Task;
use App\Models\TaskAssignedTo;
use App\Models\TaskHistory;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TaskController extends ApiController
{
    public function add(Request $request): JsonResponse
    {
        try {
            $validate = Validator::make($request->all(), [
                'name' => 'required|max:50',
                'status_id' => 'required|exists:statuses,id',
            ]);

            if ($validate->fails()) {
                return $this->sendError("Bad request", $validate->messages()->toArray());
            }

            $authUser = Auth::user();
            $status = Status::find($request->get("status_id"));
            $foundUser = BoardMembers::where("board_id", $status->board->id)->where("user_id", $authUser->id)->first();

            if (!$foundUser || $foundUser->role !== "Admin") {
                return $this->sendError("Not allowed to perform this action", [], Response::HTTP_METHOD_NOT_ALLOWED);
            }

            $task = new Task();
            $task->name = $request->get("name");
            $task->status_id = $request->get("status_id");
            $task->save();

            $taskHistory = new TaskHistory();
            $taskHistory->task_id = $task->id;
            $taskHistory->user_id = $authUser->id;
            $taskHistory->action = "$authUser->email" . " created the task";
            $taskHistory->save();

            return $this->sendResponse($task->toArray(), Response::HTTP_CREATED);
        } catch (Exception $exception) {
            Log::error($exception);
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update($id, Request $request): JsonResponse
    {
        try {
            $task = Task::where("id", $id)->with("status")->first();
            $authUser = Auth::user();
            $foundUser = BoardMembers::where("board_id", $task->status->board->id)->where("user_id", $authUser->id)->first();

            if (!$foundUser || $foundUser->role !== "Admin") {
                return $this->sendError("Not allowed to perform this action", [], Response::HTTP_METHOD_NOT_ALLOWED);
            }

            if (!$task) {
                return $this->sendError('task not found!', [], Response::HTTP_NOT_FOUND);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|max:50',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Bad request!', $validator->messages()->toArray());
            }

            $prevTaskName = $task->name;
            $task->name = $request->get('name');
            $task->save();

            $taskHistory = new TaskHistory();
            $taskHistory->task_id = $task->id;
            $taskHistory->user_id = $authUser->id;
            $taskHistory->action = "$authUser->email" . " changed task name from {$prevTaskName} to {$task->name}";
            $taskHistory->save();

            $getAllBoardMemembers = BoardMembers::where("board_id", $task->status->board->id)->get();
            $users = [];

            foreach ($getAllBoardMemembers as $member) {
                $users[] = $member->user_id;
            }

            event(new SendEventToClient($taskHistory, $users, "task_history"));

            return $this->sendResponse($task->toArray());
        } catch (Exception $exception) {
            Log::error($exception);
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function archive($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $task = Task::where("id", $id)->with("status")->first();

            if (!$task) {
                return $this->sendError('task not found!', [], Response::HTTP_NOT_FOUND);
            }

            if ($user->id != $task->status->board->owner_id) {
                return $this->sendError("Not allowed to perform this action", [], Response::HTTP_METHOD_NOT_ALLOWED);
            }


            $users = [];
            foreach ($task->status->board->getMembers as $member) {
                $users[] = $member->user_id;
            }

            if (!$task->isArchived) {
                $archiveTask = new ArchivedTasks();
                $archiveTask->task_id = $task->id;
                $archiveTask->archived_by = $user->id;
                $archiveTask->save();

                event(new SendEventToClient($archiveTask, $users, "archive_task"));

                $taskHistory = new TaskHistory();
                $taskHistory->task_id = $task->id;
                $taskHistory->user_id = $user->id;
                $taskHistory->action = "$user->email" . " archived the task";
                $taskHistory->save();

                event(new SendEventToClient($taskHistory, $users, "task_history"));
            } else {
                $archiveTask = ArchivedTasks::where("task_id", $task->id)->first();
                $archiveTask->delete();

                event(new SendEventToClient($task, $users, "unarchived_task"));

                $taskHistory = new TaskHistory();
                $taskHistory->task_id = $task->id;
                $taskHistory->user_id = $user->id;
                $taskHistory->action = "$user->email" . " unarchived the task";
                $taskHistory->save();

                event(new SendEventToClient($taskHistory, $users, "task_history"));
            }

            $task->isArchived = !$task->isArchived;
            $task->save();

            return $this->sendResponse($task->toArray());
        } catch (Exception $exception) {
            Log::error($exception);
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function delete($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $task = Task::where('id', $id)
                ->with('status')
                ->first();

            if (!$task) {
                return $this->sendError('task not found!', [], Response::HTTP_NOT_FOUND);
            }

            $foundUser = BoardMembers::where("board_id", $task->status->board->id)->where("user_id", $user->id)->first();
            if (!$foundUser || $foundUser->role !== "Admin") {
                return $this->sendError("Not allowed to perform this action", [], Response::HTTP_METHOD_NOT_ALLOWED);
            }

            DB::beginTransaction();
            $task->delete();
            DB::commit();

            return $this->sendResponse([], Response::HTTP_NO_CONTENT);
        } catch (Exception $exception) {
            Log::error($exception);
            return $this->sendError($exception, [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function changeTaskStatus(Request $request): JsonResponse
    {
        try {

            $validate = Validator::make($request->all(), [
                'task_id' => 'required',
            ]);

            if ($validate->fails()) {
                return $this->sendError("Bad request", $validate->messages()->toArray());
            }

            $task = Task::find($request->get('task_id'));

            if (!$task) {
                return $this->sendError('task not found!', [], Response::HTTP_NOT_FOUND);
            }

            $authUser = Auth::user();
            $isUserAssignedToTask = TaskAssignedTo::where("assigned_to", $authUser->id)->where("task_id", $task->id)->first();
            $getUserRole = BoardMembers::where("user_id", $authUser->id)->where("board_id", $task->status->board->id)->first();

            if (!$isUserAssignedToTask && $getUserRole->role !== "Admin") {
                return $this->sendError("Not allowed to perform this action", [], Response::HTTP_METHOD_NOT_ALLOWED);
            }


            $task->isActive = !$task->isActive;
            $task->save();

            $getAllBoardMemembers = BoardMembers::where("board_id", $task->status->board->id)->get();
            $users = [];

            foreach ($getAllBoardMemembers as $member) {
                $users[] = $member->user_id;
            }

            event(new SendEventToClient($task, $users, "change_task_status"));

            $taskHistory = new TaskHistory();
            $taskHistory->task_id = $task->id;
            $taskHistory->user_id = $authUser->id;
            $taskHistory->action = "$authUser->email" . ' changed task status to ' . ($task->isActive ? 'active' : 'inactive');
            $taskHistory->save();

            event(new SendEventToClient($taskHistory, $users, "task_history"));

            return $this->sendResponse($task);
        } catch (Exception $exception) {
            Log::error($exception);
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getTaskHistory($task_id): JsonResponse
    {
        try {
            $task = Task::where("id", $task_id)->with("status")->first();
            if (!$task) {
                return $this->sendError('task not found!', [], Response::HTTP_NOT_FOUND);
            }

            $authUser = Auth::user();
            $foundUser = BoardMembers::where("board_id", $task->status->board->id)->where("user_id", $authUser->id)->first();

            if (!$foundUser) {
                return $this->sendError("Not allowed to perform this action", [], Response::HTTP_METHOD_NOT_ALLOWED);
            }

            $taskHistory = TaskHistory::where('task_id', $task->id)->orderBy("created_at", "DESC")->paginate(30);

            $result = [
                "task_history" => $taskHistory->items(),
                "currentPage" => $taskHistory->currentPage(),
                "hasMorePages" => $taskHistory->hasMorePages(),
                "lastPage" => $taskHistory->lastPage()
            ];

            return $this->sendResponse($result);
        } catch (Exception $exception) {
            Log::error($exception);

            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
