<?php

namespace App\Http\Controllers;

use App\Models\UserNotifications as ModelUserNotifications;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Events\SendEventToClient;

class UserNotifications extends ApiController
{
    public function getNotifications()
    {
        try {
            $authUser = Auth::user();
            $notifictaionsQuery = ModelUserNotifications::query();
            $notifications = $notifictaionsQuery->where("user_id", $authUser->id)->orderBy("created_at", "desc")->paginate(20);

            $result = [
                "notifications" => $notifications->items(),
                "currentPage" => $notifications->currentPage(),
                "hasMorePages" => $notifications->hasMorePages(),
                "lastPage" => $notifications->lastPage(),
            ];

            return $this->sendResponse($result);
        } catch (Exception $exception) {
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function deleteUserNotification($id)
    {
        try {
            $authUser = Auth::user();
            $notification = ModelUserNotifications::where("user_id", $authUser->id)->where("id", $id)->first();

            if (!$notification) {
                return $this->sendError("Not allowed to perform this action", [], Response::HTTP_METHOD_NOT_ALLOWED);
            }

            event(new SendEventToClient(["notification_id" => $notification->id], [$notification->user_id], "delete_notification"));

            DB::beginTransaction();
            $notification->delete();
            DB::commit();

            return $this->sendResponse([], Response::HTTP_NO_CONTENT);
        } catch (Exception $exception) {
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function hasUserUnseenNotifications()
    {
        try {
            $authUser = Auth::user();
            $checkIfUnSeenNotificationExist = ModelUserNotifications::where("user_id", $authUser->id)->where("seen", 0)->get();

            $result = [
                "data" => false
            ];

            if (count($checkIfUnSeenNotificationExist) > 0) {
                $result["data"] = true;
            }

            return $this->sendResponse($result);
        } catch (Exception $exception) {
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function markNotificationAsSeen(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                "id" => "required|exists:user_notifications,id",
            ]);

            if ($validate->fails()) {
                return $this->sendError($validate->messages()->toArray());
            }

            $authUser = Auth::user();
            $notification = ModelUserNotifications::find($request->get("id"));

            if ($notification->user_id !== $authUser->id) {
                return $this->sendError("Not allowed to perform this action", [], Response::HTTP_METHOD_NOT_ALLOWED);
            }

            if ($notification->seen == 1) {
                return $this->sendError("Notification is already marked as seen", []);
            }

            $notification->seen = 1;
            $notification->save();

            return $this->sendResponse($notification);
        } catch (Exception $exception) {
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
