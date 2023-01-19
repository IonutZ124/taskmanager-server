<?php

namespace App\Http\Controllers;

use App\Models\ArchivedTasks;
use App\Models\Board;
use App\Models\PasswordReset;
use App\Models\User;
use App\Notifications\ForgotPassword;
use App\Notifications\VerifyEmail;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UserController extends ApiController
{

    public function getUser()
    {
        try {
            $user = Auth::user();

            return $this->sendResponse([
                "user" => $user
            ]);
        } catch (Exception $exception) {
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getUserById($id)
    {
        try {
            $user = User::find($id);
            if (!$user) {
                return $this->sendError("Not found", [], Response::HTTP_NOT_FOUND);
            }
            return $this->sendResponse([
                "user" => $user,
                "profile_image" => $user->image
            ]);
        } catch (Exception $exception) {
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function register(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'name' => 'required',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|confirmed'
            ]);

            if ($validate->fails()) {
                return $this->sendError('Bad request!', $validate->messages()->toArray());
            }

            $user = new User();
            $user->name = $request->get("name");
            $user->email = $request->get("email");
            $user->password = Hash::make($request->get("password"));
            $user->verify_token = Str::random(10);
            $user->save();

            $user->notify(new VerifyEmail($user->verify_token));

            return $this->sendResponse(["Code for email verification send"], Response::HTTP_CREATED);
        } catch (Exception $exception) {
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function login(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required'
            ]);

            if ($validate->fails()) {
                return $this->sendError('Bad request!', $validate->messages()->toArray());
            }

            $error = false;
            $user = User::where('email', $request->get("email"))->first();

            if (!$user) {
                $error = true;
            } else {
                if (!Hash::check($request->get("password"), $user->password)) {
                    $error = true;
                }
            }
            if ($error) {
                return $this->sendError('Bad credentials!');
            }
            if (!$user->email_verified_at) {
                return $this->sendError('User didn\'t verify email address', [], Response::HTTP_NOT_ACCEPTABLE);
            }

            $token = $user->createToken("app");

            return $this->sendResponse([
                "token" => $token->plainTextToken,
                "user" => $user->toArray()
            ]);
        } catch (Exception $exception) {
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function verifyEmail(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                "email" => 'required|email|exists:users,email',
                "code" => 'required'
            ]);

            if ($validate->fails()) {
                return $this->sendError('Bad request!', $validate->messages()->toArray());
            }

            $user = User::where("email", $request->get("email"))->where("verify_token", $request->get("code"))->first();

            if (!$user) {
                return $this->sendError('Bad code or email!');
            }

            $user->email_verified_at = now();
            $user->verify_token = null;
            $user->save();

            return $this->sendResponse([
                'data' => 'Email verified'
            ]);
        } catch (Exception $exception) {
            Log::error($exception);
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function resendVerifyEmailCode(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                "email" => 'required|email|exists:users,email'
            ]);

            if ($validate->fails()) {
                return $this->sendError('Bad request!', $validate->messages()->toArray());
            }

            $user = User::where("email", $request->get("email"))->first();

            if ($user->email_verified_at) {
                return $this->sendError('Email already verified', [], Response::HTTP_NOT_ACCEPTABLE);
            }

            $user->notify(new VerifyEmail($user->verify_token));

            return $this->sendResponse([
                'data' => 'Code for email verification sent!'
            ]);
        } catch (Exception $exception) {
            Log::error($exception);
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getUserBoards()
    {
        try {
            $user = Auth::user();
            $boards = Board::query();
            $getBoards = $boards->where([["owner_id", $user->id], ["isArchived", false]])->paginate(10);

            $result = [
                "boards" => [],
                "currentPage" => $getBoards->currentPage(),
                "hasMorePages" => $getBoards->hasMorePages(),
                "lastPage" => $getBoards->lastPage()
            ];

            foreach ($getBoards as $board) {
                $result["boards"][] = $board;
            }

            return $this->sendResponse($result);
        } catch (Exception $exception) {
            Log::error($exception);
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getUserArchivedBoards()
    {
        try {
            $user = Auth::user();
            $boards = Board::query();
            $getBoards = $boards->where([["owner_id", $user->id], ["isArchived", true]])->paginate(10);
            $result = [
                "boards" => [],
                "currentPage" => $getBoards->currentPage(),
                "hasMorePages" => $getBoards->hasMorePages(),
                "lastPage" => $getBoards->lastPage()
            ];

            foreach ($getBoards as $board) {
                $result["boards"][] = $board;
            }

            return $this->sendResponse($result);
        } catch (Exception $exception) {
            Log::error($exception);
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getUserArchivedTasks()
    {
        try {
            $authUser = Auth::user();
            $archivedTasks = ArchivedTasks::query();
            $getArchivedTaskForUser = $archivedTasks->where("archived_by", $authUser->id)->paginate(10);

            $result = [
                "tasks" => [],
                "currentPage" => $getArchivedTaskForUser->currentPage(),
                "hasMorePages" => $getArchivedTaskForUser->hasMorePages(),
                "lastPage" => $getArchivedTaskForUser->lastPage()
            ];

            foreach ($getArchivedTaskForUser->items() as $archivedTask) {
                $task = $archivedTask->getTask;
                $result["tasks"][] = $task;
            }

            return $this->sendResponse($result);
        } catch (Exception $exception) {
            Log::error($exception);
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function forgotPassword(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                "email" => 'required|email|exists:users,email'
            ]);

            if ($validate->fails()) {
                return $this->sendError('Bad request!', $validate->messages()->toArray());
            }

            $user = User::where("email", $request->get("email"))->first();

            $resetPassword = new PasswordReset();
            $resetPassword->email = $user->email;
            $resetPassword->token = Str::random(10);
            $resetPassword->save();

            $user->notify(new ForgotPassword($resetPassword->token));

            return $this->sendResponse([
                'data' => 'Code for reset password sent!'
            ]);
        } catch (Exception $exception) {
            Log::error($exception);
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function resetPassword(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                "email" => 'required|email|exists:users,email',
                "token" => 'required',
                "password" => 'required|confirmed'
            ]);

            if ($validate->fails()) {
                return $this->sendError('Bad request!', $validate->messages()->toArray());
            }

            $email = $request->get('email');
            $token = $request->get('token');
            $password = $request->get('password');

            if (!PasswordReset::where('email', $email)->where('token', $token)->get()) {
                return $this->sendError('Email or token incorrect!');
            }

            $user = User::where("email", $email)->first();
            $user->password = Hash::make($password);
            $user->save();

            $passwordReset = PasswordReset::where('email', $email);
            DB::beginTransaction();
            $passwordReset->delete();
            DB::commit();

            return $this->sendResponse([
                'data' => 'Password changed!'
            ]);
        } catch (Exception $exception) {
            Log::error($exception);
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function uploadUserProfileImage(Request $request)
    {
        try {
            if ($request->has('image')) {
                $file = $request->file('image');
                $validate = Validator::make($request->all(), [
                    'image'=> 'mimes:jpeg,jpg,png,gif',
                ]);
                if($validate->fails()){
                    return $this->sendError($validate->messages()->toArray());
                }
                $authUser = Auth::user();
                $getUser = User::find($authUser->id);
                if ($getUser->image) {
                    $arrImgName = explode("/", $getUser->image);
                    $lastImageName = $arrImgName[count($arrImgName) - 1];
                    Storage::delete("public/user/" . $lastImageName);
                }
                $getRandomString = Str::random(8);

                $filename = "{$getRandomString}." . $file->getClientOriginalExtension();

                $path = '/public/user';

                Storage::putFileAs($path, $file, $filename);

                $getUser->image = "storage/user/{$getRandomString}." . $file->getClientOriginalExtension();
                $getUser->save();

                return $this->sendResponse([
                    "Image has been uploaded"
                ]);
            }
        } catch (Exception $exception) {
            Log::error($exception);
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function deleteAccount(){
        try{
            $authUser = Auth::user();
            $user = User::find($authUser->id);
            $user->delete();
        }
        catch (Exception $exception) {
            Log::error($exception);
            return $this->sendError('Something went wrong, please contact administrator!', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
