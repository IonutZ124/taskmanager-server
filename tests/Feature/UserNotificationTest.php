<?php

namespace Tests\Feature;

use App\Http\Controllers\UserNotifications;
use App\Models\User;
use App\Models\UserNotifications as ModelsUserNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserNotificationTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    use DatabaseTransactions;

    public function test_get_user_notification()
    {
        $userNotificationController = new UserNotifications();
        $getRandomUser = User::inRandomOrder()->first();

        Auth::shouldReceive('user')->once()->andReturn($getRandomUser);

        $request = Request::create("/get-user-notifications", 'GET');

        $response = $userNotificationController->getNotifications($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_delete_user_notification()
    {
        $userNotificationController = new UserNotifications();
        $getRandomNotification = ModelsUserNotifications::inRandomOrder()->first();

        $user = User::find($getRandomNotification->user_id);
        Auth::shouldReceive('user')->once()->andReturn($user);

        $request = Request::create("/delete-notification", 'DELETE');

        $response = $userNotificationController->deleteUserNotification($getRandomNotification->id);

        $this->assertEquals(204, $response->getStatusCode());
    }

    public function test_delete_user_notification_now_allowed()
    {
        $userNotificationController = new UserNotifications();
        $getRandomNotification = ModelsUserNotifications::inRandomOrder()->first();

        $user = User::factory()->create();
        Auth::shouldReceive('user')->once()->andReturn($user);

        $request = Request::create("/delete-notification", 'DELETE');

        $response = $userNotificationController->deleteUserNotification($getRandomNotification->id);

        $this->assertEquals(405, $response->getStatusCode());
    }

    public function test_has_user_unseen_notifications()
    {
        $userNotificationController = new UserNotifications();

        $user = User::inRandomOrder()->first();
        Auth::shouldReceive('user')->once()->andReturn($user);

        $request = Request::create("/has-user-unseen-notifications", 'GET');

        $response = $userNotificationController->hasUserUnseenNotifications();

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_markNotification_asSeen()
    {
        $userNotificationController = new UserNotifications();
        $getRandomNotification = ModelsUserNotifications::inRandomOrder()->first();
        $getUserID = $getRandomNotification->user_id;

        $user = User::find($getUserID);
        Auth::shouldReceive('user')->once()->andReturn($user);

        $request = Request::create("/mark-notification-as-seen", 'PUT', [
            "id" => $getRandomNotification->id
        ]);

        $response = $userNotificationController->markNotificationAsSeen($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_markNotification_asSeen_not_allowed()
    {
        $userNotificationController = new UserNotifications();
        $getRandomNotification = ModelsUserNotifications::inRandomOrder()->first();

        $user = User::factory()->create();
        Auth::shouldReceive('user')->once()->andReturn($user);

        $request = Request::create("/mark-notification-as-seen", 'PUT', [
            "id" => $getRandomNotification->id
        ]);

        $response = $userNotificationController->markNotificationAsSeen($request);
        $this->assertEquals(405, $response->getStatusCode());
    }
}