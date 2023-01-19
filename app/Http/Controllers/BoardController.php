<?php

namespace App\Http\Controllers;

use App\Events\SendEventToClient;
use App\Models\Board;
use App\Models\BoardInvites;
use App\Models\BoardMembers;
use App\Models\User;
use App\Models\UserNotifications;
use App\Notifications\SendBoardInvite;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as FacadesNotification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BoardController extends ApiController
{

    public function add(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'name' => 'required|max:50',
            ]);

            if ($validate->fails()) {
                return $this->sendError("Bad request", $validate->messages()->toArray());
            }

            $authUser = Auth::user();
            $board = new Board();
            $board->name = $request->get("name");
            $board->owner_id = $authUser->id;
            $board->slug = Str::random(15);
            $board->save();

            $getAllBoardMemembers = BoardMembers::where("board_id", $board->id)->get();
            $users = [];

            foreach ($getAllBoardMemembers as $member) {
                $users[] = $member->user_id;
            }

            event(new SendEventToClient($board, $users, "board"));

            $boardMember = new BoardMembers();
            $boardMember->board_id = $board->id;
            $boardMember->user_id = $board->owner_id;
            $boardMember->role = "Admin";
            $boardMember->isBoardOwner = 1;
            $boardMember->save();

            return $this->sendResponse($board->toArray(), Response::HTTP_CREATED);
        } catch (Exception $exception) {
            Log::error($exception);
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update($id, Request $request)
    {
        try {
            $board = Board::find($id);

            if (!$board) {
                return $this->sendError("Board not found", [], Response::HTTP_NOT_FOUND);
            }

            $authUser = Auth::user();
            $foundUser = BoardMembers::where("board_id", $id)->where("user_id", $authUser->id)->first();

            if (!$foundUser || $foundUser->role !== "Admin") {
                return $this->sendError("Not allowed to perform this action", [], Response::HTTP_METHOD_NOT_ALLOWED);
            }

            $validate = Validator::make($request->all(), [
                'name' => 'required|max:50',
            ]);

            if ($validate->fails()) {
                return $this->sendError("Bad request", $validate->messages()->toArray());
            }

            $board->name = $request->get("name");
            $board->save();

            return $this->sendResponse($board->toArray());
        } catch (Exception $exception) {
            Log::error($exception);
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function archive($id)
    {
        try {
            $board = Board::find($id);

            if (!$board) {
                return $this->sendError('Board not found!', [], Response::HTTP_NOT_FOUND);
            }
            $user = Auth::user();

            $foundUser = BoardMembers::where("board_id", $id)->where("user_id", $user->id)->first();

            if (!$foundUser || ($user->id !== $board->owner_id)) {
                return $this->sendError("Not allowed to perform this action", [], Response::HTTP_METHOD_NOT_ALLOWED);
            }
            $board->isArchived = $board->isArchived ? false : true;
            $board->save();

            $getAllBoardMemembers = BoardMembers::where("board_id", $board->id)->get();
            $users = [];

            foreach ($getAllBoardMemembers as $member) {
                $users[] = $member->user_id;
            }

            event(new SendEventToClient($board, $users, "archive_board"));

            return $this->sendResponse($board->toArray());
        } catch (Exception $exception) {
            Log::error($exception);
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function sendInvite(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'board_id' => 'required|exists:boards,id',
                'email' => 'required|email'
            ]);

            if ($validate->fails()) {
                return $this->sendError($validate->messages()->toArray());
            }

            $authUser = Auth::user();
            $foundUser = BoardMembers::where("board_id", $request->get("board_id"))->where("user_id", $authUser->id)->first();

            if (!$foundUser || $foundUser->role !== "Admin") {
                return $this->sendError("Not allowed to perform this action", [], Response::HTTP_METHOD_NOT_ALLOWED);
            }

            $getInvitedUser = User::where("email", $request->get("email"))->first();

            if ($getInvitedUser) {
                $foundInvitedUserAsMemeber = BoardMembers::where("board_id", $request->get("board_id"))->where("user_id", $getInvitedUser->id)->first();
                if ($foundInvitedUserAsMemeber) {
                    return $this->sendError("User already is a board member!", [], Response::HTTP_NOT_ACCEPTABLE);
                }
            }

            $lastUserboardInvite = BoardInvites::where('email', $request->get('email'))
                ->orderBy('created_at', 'DESC')
                ->first();

            if ($lastUserboardInvite) {
                if ($lastUserboardInvite->created_at > Carbon::now()->subHour()) {
                    return $this->sendError('You can invite this user again after 1 hour!', [], Response::HTTP_NOT_ACCEPTABLE);
                }
            }

            $code = Str::random(6);

            FacadesNotification::route("mail", $request->get("email"))->notify(new SendBoardInvite($code, $authUser->name));

            $boardInvites = new BoardInvites();
            $boardInvites->board_id = $request->get("board_id");
            $boardInvites->email = $request->get("email");
            $boardInvites->code = $code;
            $boardInvites->save();

            if ($getInvitedUser) {
                $userNotification = new UserNotifications();
                $userNotification->user_id = $getInvitedUser->id;
                $userNotification->message = "{$authUser->name} has invited you to join his board, code: {$code}";
                $userNotification->save();
                event(new SendEventToClient($userNotification, [$userNotification->user_id], "notification"));
            }

            return $this->sendResponse(['Code for joining the board has been sent to the user email.']);
        } catch (Exception $exception) {
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function acceptInvite(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'code' => 'required'
            ]);

            if ($validate->fails()) {
                return $this->sendError($validate->messages()->toArray());
            }

            $authUser = Auth::user();
            $checkUser = BoardInvites::where("code", $request->get("code"))->first();

            if (!$checkUser) {
                return $this->sendError("Invalid code", []);
            }

            if ($authUser->email !== $checkUser->email) {
                return $this->sendError("Not allowed to use this code", [], Response::HTTP_NOT_ACCEPTABLE);
            }
            $boardMember = new BoardMembers();
            $boardMember->board_id = $checkUser->board_id;
            $boardMember->user_id = $authUser->id;
            $boardMember->save();
            $checkUser->delete();

            return $this->sendResponse(["You successfully joined the board!"]);
        } catch (Exception $exception) {
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function delete($id)
    {
        try {
            $board = Board::find($id);

            if (!$board) {
                return $this->sendError("Board not found", [], Response::HTTP_NOT_FOUND);
            }

            $user = Auth::user();

            $getAllBoardMemembers = BoardMembers::where("board_id", $board->id)->get();
            $users = [];
            foreach ($getAllBoardMemembers as $member) {
                $users[] = $member->user_id;
            }

            if ($user->id !== $board->owner_id && !$user->isSuperAdmin) {
                return $this->sendError("Not allowed to delete this board", [], Response::HTTP_METHOD_NOT_ALLOWED);
            }

            DB::beginTransaction();
            $board->delete();

            event(new SendEventToClient(["board_id" => $id], $users, "delete_board"));

            DB::commit();

            return $this->sendResponse([], Response::HTTP_NO_CONTENT);
        } catch (Exception $exception) {
            Log::error($exception);
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getBoardsWhereUserIsMember()
    {
        try {
            $authUser = Auth::user();
            $getUsers = BoardMembers::query();
            $getUser = $getUsers->where("user_id", $authUser->id)->where("isBoardOwner", 0)->paginate(10);

            $result = [
                "boards" => [],
                "currentPage" => $getUser->currentPage(),
                "hasMorePages" => $getUser->hasMorePages(),
                "lastPage" => $getUser->lastPage()
            ];

            foreach ($getUser->items() as $userBoardMember) {
                $board = $userBoardMember->getBoards;

                if (!$board->isArchived) {
                    $result["boards"][] = $board;
                }
            }

            return $this->sendResponse($result);
        } catch (Exception $exception) {
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getBoard($slug)
    {
        try {
            $board = Board::where("slug", $slug)->first();

            if (!$board) {
                return $this->sendError("Board not found", [], Response::HTTP_NOT_FOUND);
            }
            $authUser = Auth::user();
            $getUserAsBoardMember = BoardMembers::where("board_id", $board->id)->where("user_id", $authUser->id)->first();
            if (!$authUser->isSuperAdmin) {
                if (!$getUserAsBoardMember || ($board->isArchived && !$getUserAsBoardMember->isBoardOwner)) {
                    return $this->sendError("Not allowed to visit this board", [], Response::HTTP_FORBIDDEN);
                }
            }

            $boardStatuses = $board->statuses;
            $boardContent = [];

            for ($i = 0; $i < count($boardStatuses); $i++) {
                $boardContent[] = $boardStatuses[$i]->toArray();
                $boardContent[$i]["tasks"] = $boardStatuses[$i]->tasks->sortByDesc("isActive")->values();
            }

            $result = [
                "board_id" => $board->id,
                "isArchived" => $board->isArchived,
                "statuses" => $boardContent,
            ];

            if ($authUser->isSuperAdmin && !$getUserAsBoardMember?->isBoardOwner) {
                $result["userRole"] = "SuperAdmin";
                $result["isBoardOwner"] = 0;
            } else {
                $result["userRole"] = $getUserAsBoardMember->role;
                $result["isBoardOwner"] = $getUserAsBoardMember->isBoardOwner;
            }

            return $this->sendResponse($result);
        } catch (Exception $exception) {
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getBoardMembers($slug, Request $request)
    {
        try {
            $board = Board::where("slug", $slug)->first();
            $authUser = Auth::user();

            if (!$board) {
                return $this->sendError("Board not found", [], Response::HTTP_NOT_FOUND);
            }

            if (!$authUser->isSuperAdmin) {
                $isAuthUserMember = BoardMembers::where("board_id", $board->id)->where("user_id", $authUser->id)->first();

                if (!$isAuthUserMember) {
                    return $this->sendError("Not allowed to perform this action", [], Response::HTTP_METHOD_NOT_ALLOWED);
                }
            }
            if ($request->has("search")) {
                $searchInput = $request->get("search");
                $users = User::where("email", "LIKE", $searchInput . "%")
                    ->whereHas('getBoardMembers', function ($q) use ($board) {
                        $q->where('board_id', $board->id);
                    })
                    ->get();

                return $this->sendResponse($users);
            } else {
                $members = BoardMembers::query();
                $boardMembers = $members->where("board_id", $board->id)->paginate(30);

                $result = [];

                foreach ($boardMembers->items() as $idx => $member) {
                    $result[] = $member->toArray();
                    $result[$idx]["email"] = $member->getUser["email"];
                }
                $data = [
                    "members" => $result,
                    "currentPage" => $boardMembers->currentPage(),
                    "hasMorePages" => $boardMembers->hasMorePages(),
                    "lastPage" => $boardMembers->lastPage(),
                    "totalMembers" => $boardMembers->total()
                ];
                return $this->sendResponse($data);
            }
        } catch (Exception $exception) {
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function changeBoardMemberRole(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                "board_id" => "required|exists:boards,id",
                "user_id" => "required|exists:users,id",
                "role" => "required"
            ]);

            if ($validate->fails()) {
                return $this->sendError($validate->messages()->toArray());
            }

            $userRole = $request->get("role");

            if ($userRole !== "Member" && $userRole !== "Admin") {
                return $this->sendError("Invalid role", []);
            }

            $authUser = Auth::user();
            $checkAuthUserRole = BoardMembers::where("board_id", $request->get("board_id"))->where("user_id", $authUser->id)->first();

            if (!$checkAuthUserRole || $checkAuthUserRole->role !== "Admin") {
                return $this->sendError("Not allowed to perform this action", [], Response::HTTP_METHOD_NOT_ALLOWED);
            }

            $checkForUser = BoardMembers::where("board_id", $request->get("board_id"))->where("user_id", $request->get("user_id"))->first();

            if (!$checkForUser) {
                return $this->sendError("This user is not a member of this board", [], Response::HTTP_NOT_FOUND);
            }

            if (!$checkAuthUserRole->isBoardOwner && $checkForUser->isBoardOwner) {
                return $this->sendError("You cannot chage the role of a board owner", [], Response::HTTP_METHOD_NOT_ALLOWED);
            }

            if ($checkAuthUserRole->user_id === $checkForUser->user_id) {
                return $this->sendError("You cannot change your role by yourself", []);
            }
            if ($checkForUser->role === $userRole) {
                return $this->sendError("User already have this role", []);
            }

            $checkForUser->role = $request->get("role");
            $checkForUser->save();

            $getAllBoardMemembers = BoardMembers::where("board_id", $request->get("board_id"))->get();
            $users = [];

            foreach ($getAllBoardMemembers as $member) {
                $users[] = $member->user_id;
            }

            event(new SendEventToClient($checkForUser, $users, "change_member_role"));

            return $this->sendResponse(["User role modified"]);
        } catch (Exception $exception) {
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function removeMemberFromBoard($id, Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                "member_id" => "required"
            ]);

            if ($validate->fails()) {
                return $this->sendError($validate->messages()->toArray());
            }

            $board = Board::find($id);

            if (!$board) {
                return $this->sendError("Board not found", [], Response::HTTP_NOT_FOUND);
            }

            $authUser = Auth::user();
            $getuser = BoardMembers::where("user_id", $authUser->id)->where("board_id", $board->id)->first();

            if (!$getuser || $getuser->role !== "Admin") {
                return $this->sendError("Not allowed to perform this action", [], Response::HTTP_METHOD_NOT_ALLOWED);
            }

            $boardMember = BoardMembers::where("user_id", $request->get("member_id"))->where("board_id", $board->id)->first();

            if (!$boardMember) {
                return $this->sendError("This user is not a board member", []);
            }

            if ($boardMember->isBoardOwner) {
                return $this->sendError("Not allowed to remove the owner of this board", [], Response::HTTP_METHOD_NOT_ALLOWED);
            }

            DB::beginTransaction();

            $getAllBoardMemembers = BoardMembers::where("board_id", $board->id)->get();
            $users = [];

            foreach ($getAllBoardMemembers as $member) {
                $users[] = $member->user_id;
            }

            event(new SendEventToClient(["member_id" => $boardMember->id], $users, "remove_board_member"));
            $boardMember->delete();
            DB::commit();

            return $this->sendResponse([]);
        } catch (Exception $exception) {
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}