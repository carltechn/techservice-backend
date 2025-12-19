<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink']);
Route::post('/reset-password', [PasswordResetController::class, 'reset']);
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware('signed')
    ->name('verification.verify');

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/email/resend', [EmailVerificationController::class, 'resend']);

    // Pusher auth endpoint
    Route::post('/pusher/auth', function (\Illuminate\Http\Request $request) {
        $pusher = new \Pusher\Pusher(
            config('broadcasting.connections.pusher.key'),
            config('broadcasting.connections.pusher.secret'),
            config('broadcasting.connections.pusher.app_id'),
            config('broadcasting.connections.pusher.options')
        );

        $channelName = $request->input('channel_name');
        $socketId = $request->input('socket_id');
        $user = $request->user();

        // Check channel authorization
        if (str_starts_with($channelName, 'private-user.')) {
            $userId = str_replace('private-user.', '', $channelName);
            if ((int) $userId !== $user->id) {
                return response()->json(['error' => 'Forbidden'], 403);
            }
            return response($pusher->authorizeChannel($channelName, $socketId));
        }

        if (str_starts_with($channelName, 'presence-ticket.') || str_starts_with($channelName, 'private-ticket.')) {
            $ticketId = preg_replace('/^(presence-|private-)ticket\./', '', $channelName);
            $ticket = \App\Models\Ticket::find($ticketId);

            if (!$ticket) {
                return response()->json(['error' => 'Ticket not found'], 404);
            }

            // Check access
            $hasAccess = $ticket->user_id === $user->id
                || $ticket->assigned_to === $user->id
                || $user->isStaff();

            if (!$hasAccess) {
                return response()->json(['error' => 'Forbidden'], 403);
            }

            if (str_starts_with($channelName, 'presence-')) {
                $presenceData = [
                    'user_id' => $user->id,
                    'user_info' => [
                        'id' => $user->id,
                        'name' => $user->full_name,
                        'role' => $user->role->display_name ?? 'User',
                    ],
                ];
                return response($pusher->authorizePresenceChannel($channelName, $socketId, $user->id, $presenceData['user_info']));
            }

            return response($pusher->authorizeChannel($channelName, $socketId));
        }

        return response()->json(['error' => 'Invalid channel'], 400);
    });

    // Tickets
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::get('/tickets/stats', [TicketController::class, 'stats']);
    Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
    Route::put('/tickets/{ticket}', [TicketController::class, 'update']);
    Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy'])->middleware('role:admin');
    Route::post('/tickets/{ticket}/assign', [TicketController::class, 'assign'])->middleware('role:admin,incharge');

    // Messages
    Route::get('/tickets/{ticket}/messages', [MessageController::class, 'index']);
    Route::post('/tickets/{ticket}/messages', [MessageController::class, 'store']);
    Route::put('/tickets/{ticket}/messages/{message}', [MessageController::class, 'update']);
    Route::delete('/tickets/{ticket}/messages/{message}', [MessageController::class, 'destroy']);
    Route::post('/tickets/{ticket}/messages/read', [MessageController::class, 'markAsRead']);
    Route::get('/messages/unread-count', [MessageController::class, 'unreadCount']);

    // File download
    Route::get('/download/{path}', [MessageController::class, 'download'])->where('path', '.*');

    // Staff routes
    Route::get('/staff', [TicketController::class, 'getStaff'])->middleware('role:admin,incharge');

    // User profile
    Route::post('/profile/picture', [UserController::class, 'updateProfilePicture']);
    Route::delete('/profile/picture', [UserController::class, 'deleteProfilePicture']);

    // Admin only routes
    Route::middleware('role:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
        Route::get('/roles', [UserController::class, 'roles']);
    });
});
