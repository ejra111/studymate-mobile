<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BootstrapController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\MeetupController;
use App\Http\Controllers\MatchmakingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\StudyAiController;
use App\Http\Controllers\StudyGroupController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'ok' => true,
        'message' => 'StudyMate Laravel API is running',
    ]);
});

Route::get('/bootstrap', [BootstrapController::class, 'index']);

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

Route::get('/ping', function () {
    return response()->json(['message' => 'pong']);
});

Route::get('/ai/health', [StudyAiController::class, 'health']);

Route::prefix('users')->group(function () {
    Route::get('/{id}', [UserController::class, 'show']);
    Route::put('/{id}', [UserController::class, 'update']);
    Route::post('/{id}/avatar', [UserController::class, 'uploadAvatar']);
    Route::post('/{id}/ktm', [UserController::class, 'uploadKtm']);
    Route::get('/{id}/study-plan', [StudyAiController::class, 'studyPlan']);
    Route::post('/{id}/coach', [StudyAiController::class, 'askCoach']);
    Route::get('/{id}/friends', [UserController::class, 'friends']);
});

Route::get('/dashboard/{userId}', [UserController::class, 'dashboard']);

Route::prefix('groups')->group(function () {
    Route::get('/', [StudyGroupController::class, 'index']);
    Route::post('/', [StudyGroupController::class, 'store']);
    Route::put('/{id}', [StudyGroupController::class, 'update']);
    Route::delete('/{id}', [StudyGroupController::class, 'destroy']);
    Route::post('/{id}/join', [StudyGroupController::class, 'join']);
    Route::post('/{id}/leave', [StudyGroupController::class, 'leave']);
    Route::post('/{id}/favorite', [StudyGroupController::class, 'toggleFavorite']);
    Route::get('/{id}/invite-link', [StudyGroupController::class, 'getInviteLink']);

    Route::get('/{id}/messages', [StudyGroupController::class, 'messages']);
    Route::post('/{id}/messages', [StudyGroupController::class, 'postMessage']);
    Route::get('/{id}/golden-hour', [StudyGroupController::class, 'getGoldenHour']);
    Route::get('/{id}/compatibility', [StudyGroupController::class, 'compatibility']);
    Route::get('/{id}/summary', [StudyGroupController::class, 'summary']);
});

Route::get('/matchmaking/{userId}', [MatchmakingController::class, 'index']);

Route::prefix('chat')->group(function () {
    Route::get('/{userId}/{friendId}', [ChatController::class, 'getMessages']);
    Route::post('/send', [ChatController::class, 'sendMessage']);
});

Route::prefix('notifications')->group(function () {
    Route::get('/{userId}', [NotificationController::class, 'index']);
    Route::post('/', [NotificationController::class, 'store']);
    Route::put('/{id}/read', [NotificationController::class, 'markRead']);
    Route::put('/{userId}/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/{id}/accept', [NotificationController::class, 'acceptInvite']);
    Route::post('/{id}/reject', [NotificationController::class, 'rejectInvite']);
});

Route::prefix('admin')->group(function () {
    Route::get('/summary', [AdminController::class, 'summary']);
    Route::get('/activities', [AdminController::class, 'activities']);

    Route::post('/programs', [AdminController::class, 'storeProgram']);
    Route::put('/programs/{id}', [AdminController::class, 'updateProgram']);
    Route::delete('/programs/{id}', [AdminController::class, 'destroyProgram']);

    Route::post('/courses', [AdminController::class, 'storeCourse']);
    Route::put('/courses/{id}', [AdminController::class, 'updateCourse']);
    Route::delete('/courses/{id}', [AdminController::class, 'destroyCourse']);

    Route::post('/locations', [AdminController::class, 'storeLocation']);
    Route::put('/locations/{id}', [AdminController::class, 'updateLocation']);
    Route::delete('/locations/{id}', [AdminController::class, 'destroyLocation']);

    Route::put('/users/{id}', [AdminController::class, 'updateUser']);
});

Route::prefix('meetups')->group(function () {
    Route::get('/user/{userId}', [MeetupController::class, 'index']);
    Route::get('/{meetupId}', [MeetupController::class, 'show']);
    Route::post('/', [MeetupController::class, 'create']);
    Route::put('/{meetupId}/participant', [MeetupController::class, 'updateStatus']);
    Route::put('/{meetupId}/status', [MeetupController::class, 'updateMeetupStatus']);
    Route::post('/location', [MeetupController::class, 'updateLocation']);
    Route::post('/checkin', [MeetupController::class, 'checkin']);
    Route::post('/emergency', [MeetupController::class, 'triggerEmergency']);
});
