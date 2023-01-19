<?php

use App\Http\Controllers\BoardController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\TaskAssignedToController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserNotifications;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post("/register", [UserController::class, "register"]);
Route::post("/login", [UserController::class, "login"]);
Route::post("/forgot-password", [UserController::class, "forgotPassword"]);
Route::post("/reset-password", [UserController::class, "resetPassword"]);
Route::post("/verify-email", [UserController::class, "verifyEmail"]);
Route::post("/resend-verify-email", [UserController::class, "resendVerifyEmailCode"]);

Route::middleware(['auth:sanctum'])->group(function () {
  Route::get("/user", [UserController::class, "getUser"]);
  Route::get("/user/{id}", [UserController::class, "getUserById"]);
  Route::get("/get-user-boards", [UserController::class, "getUserBoards"]);
  Route::post("/upload-profile-image", [UserController::class, "uploadUserProfileImage"]);
  Route::delete("/delete-account", [UserController::class, "deleteAccount"]);

  Route::get("/get-joined-boards", [BoardController::class, "getBoardsWhereUserIsMember"]);
  Route::get("/get-user-archived-boards", [UserController::class, "getUserArchivedBoards"]);
  Route::get("/get-user-archived-tasks", [UserController::class, "getUserArchivedTasks"]);
  Route::get("/get-user-notifications", [UserNotifications::class, "getNotifications"]);
  Route::get("/has-user-unseen-notifications", [UserNotifications::class, "hasUserUnseenNotifications"]);
  Route::put("/mark-notification-as-seen", [UserNotifications::class, "markNotificationAsSeen"]);
  Route::delete("/delete-notification/{id}", [UserNotifications::class, "deleteUserNotification"]);

  Route::get("/board/{slug}", [BoardController::class, "getBoard"]);
  Route::post("/create-board", [BoardController::class, "add"]);
  Route::get("/get-board-members/{slug}", [BoardController::class, "getBoardMembers"]);
  Route::put("/change-boardmember-role", [BoardController::class, "changeBoardMemberRole"]);
  Route::delete("/remove-member-from-board/{id}", [BoardController::class, "removeMemberFromBoard"]);
  Route::post("/send-invite", [BoardController::class, "sendInvite"]);
  Route::post("/accept-board-invite", [BoardController::class, "acceptInvite"]);
  Route::put("/update-board/{id}", [BoardController::class, "update"]);
  Route::put("/archive-board/{id}", [BoardController::class, "archive"]);
  Route::delete("/delete-board/{id}", [BoardController::class, "delete"]);

  Route::get("/get-statuses/{id}", [StatusController::class, "getAllStatusesForBoard"]);
  Route::post("/create-status", [StatusController::class, "add"]);
  Route::put("/update-status/{id}", [StatusController::class, "update"]);
  Route::delete("/delete-status/{id}", [StatusController::class, "delete"]);

  Route::get("/get-tasks/{id}", [TaskController::class, "getAllTasksForStatus"]);
  Route::post("/create-task", [TaskController::class, "add"]);
  Route::put("/update-task/{id}", [TaskController::class, "update"]);
  Route::post("/change-task-status", [TaskController::class, "changeTaskStatus"]);
  Route::get("/get-task-assigned-users/{id}", [TaskAssignedToController::class, "getAssignedUsers"]);
  Route::post("/assign-task-to-user", [TaskAssignedToController::class, "assignTaskToUser"]);
  Route::put("/archive-task/{id}", [TaskController::class, "archive"]);
  Route::get("/get-task-history/{id}", [TaskController::class, "getTaskHistory"]);
  Route::delete("/delete-task/{id}", [TaskController::class, "delete"]);

  Route::post("/create-comment", [TaskCommentController::class, "add"]);
  Route::get("/get-comments/{id}", [TaskCommentController::class, "get"]);
  Route::delete("/delete-comment/{id}", [TaskCommentController::class, "delete"]);
});
