<?php

namespace App\Http\Controllers;

use App\Events\SendEventToClient;
use App\Models\Board;
use App\Models\BoardMembers;
use App\Models\Status;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class StatusController extends ApiController
{
    public function add(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'name' => 'required|max:50',
                'board_id' => 'required|exists:boards,id',
            ]);

            if ($validate->fails()) {
                return $this->sendError("Bad request", $validate->messages()->toArray());
            }

            $authUser = Auth::user();
            $foundUser = BoardMembers::where("board_id", $request->get("board_id"))->where("user_id", $authUser->id)->first();

            if (!$foundUser || $foundUser->role !== "Admin") {
                return $this->sendError("Not allowed to perform this action", [], Response::HTTP_METHOD_NOT_ALLOWED);
            }

            $status = new Status();
            $status->name = $request->get("name");
            $status->board_id = $request->get("board_id");
            $status->save();

            $getAllBoardMemembers = BoardMembers::where("board_id", $status->board->id)->get();
            $users = [];

            foreach ($getAllBoardMemembers as $member) {
                $users[] = $member->user_id;
            }

            event(new SendEventToClient($status, $users, "new_status"));

            return $this->sendResponse($status->toArray(), Response::HTTP_CREATED);
        } catch (Exception $exception) {
            Log::error($exception);
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getAllStatusesForBoard($boardId)
    {
        try {
            $board = Board::find($boardId);
            $statuses = $board->statuses;

            if (!$statuses) {
                return $this->sendError('statuses not found!', [], Response::HTTP_NOT_FOUND);
            }

            $authUser = Auth::user();
            $foundUser = BoardMembers::where("board_id", $boardId)->where("user_id", $authUser->id)->first();

            if (!$foundUser) {
                return $this->sendError("Not allowed to perform this action", [], Response::HTTP_METHOD_NOT_ALLOWED);
            }

            return $this->sendResponse($statuses->toArray());
        } catch (Exception $exception) {
            Log::error($exception);
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update($id, Request $request)
    {
        try {
            $status = Status::find($id);

            if (!$status) {
                return $this->sendError('status not found!', [], Response::HTTP_NOT_FOUND);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|max:50',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Bad request!', $validator->messages()->toArray());
            }

            $authUser = Auth::user();
            $foundUser = BoardMembers::where("board_id", $status->board->id)->where("user_id", $authUser->id)->first();

            if (!$foundUser || $foundUser->role !== "Admin") {
                return $this->sendError("Not allowed to perform this action", [], Response::HTTP_METHOD_NOT_ALLOWED);
            }

            $name = $request->get('name');
            $status->name = $name;
            $status->save();

            return $this->sendResponse($status->toArray());
        } catch (Exception $exception) {
            Log::error($exception);
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function delete($id)
    {
        try {
            $status = Status::find($id);

            if (!$status) {
                return $this->sendError('status not found!', [], Response::HTTP_NOT_FOUND);
            }

            $authUser = Auth::user();
            $foundUser = BoardMembers::where("board_id", $status->board->id)->where("user_id", $authUser->id)->first();

            if (!$foundUser || $foundUser->role !== "Admin") {
                return $this->sendError("Not allowed to perform this action", [], Response::HTTP_METHOD_NOT_ALLOWED);
            }

            $getAllBoardMemembers = BoardMembers::where("board_id", $status->board->id)->get();
            $users = [];

            foreach ($getAllBoardMemembers as $member) {
                $users[] = $member->user_id;
            }

            DB::beginTransaction();

            $status->delete();
            event(new SendEventToClient(["status_id" => $id], $users, "delete_status"));

            DB::commit();

            return $this->sendResponse([], Response::HTTP_NO_CONTENT);
        } catch (Exception $exception) {
            Log::error($exception);
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}