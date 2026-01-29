<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\PlansController;
use App\Http\Controllers\Api\ProfilesController;
use App\Http\Controllers\Api\DocumentsController;
use App\Http\Controllers\Api\JobsController;
use App\Http\Controllers\Api\JobApplicationsController;
use App\Http\Controllers\Api\InterviewsController;
use App\Http\Controllers\Api\SubscriptionsController;
use App\Http\Controllers\Api\PaymentsController;
use App\Http\Controllers\Api\VerificationRequestsController;
use App\Http\Controllers\Api\AiScreeningsController;
use App\Http\Controllers\Api\AuditLogsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MessagesController;
use App\Http\Controllers\Api\UsersController;
/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/
Route::get('/health', [HealthController::class, 'index']);
Route::get('/plans', [PlansController::class, 'index']);

// Public job browsing
Route::get('/public/jobs', [JobsController::class, 'publicIndex']);
Route::get('/public/jobs/{job}', [JobsController::class, 'publicShow']);

/*
|--------------------------------------------------------------------------
| Auth
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me',      [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

/*
|--------------------------------------------------------------------------
| Protected (MUST be auth:sanctum so $request->user() works)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/debug/whoami', function (\Illuminate\Http\Request $request) {
        $u = $request->user();
        return response()->json([
            'authed' => (bool) $u,
            'id' => $u?->id,
            'role' => $u?->role,
            'email' => $u?->email,
            'token_id' => $u?->currentAccessToken()?->id,
            'token_name' => $u?->currentAccessToken()?->name,
        ]);
    });
    
    // Profile (me)
    Route::get('/me', [ProfilesController::class, 'me']);
    Route::put('/me/applicant', [ProfilesController::class, 'upsertApplicant']);
    Route::put('/me/employer', [ProfilesController::class, 'upsertEmployer']);
    Route::put('/me/agency', [ProfilesController::class, 'upsertAgency']);

    // Documents
    Route::get('/documents', [DocumentsController::class, 'index']);
    Route::post('/documents', [DocumentsController::class, 'store']);
    Route::delete('/documents/{document}', [DocumentsController::class, 'destroy']);

    // Jobs (owner)
    Route::get('/jobs', [JobsController::class, 'index']);
    Route::post('/jobs', [JobsController::class, 'store']);
    Route::get('/jobs/{job}', [JobsController::class, 'show']);
    Route::put('/jobs/{job}', [JobsController::class, 'update']);
    Route::post('/jobs/{job}/publish', [JobsController::class, 'publish']);
    Route::post('/jobs/{job}/archive', [JobsController::class, 'archive']);
    Route::delete('/jobs/{job}', [JobsController::class, 'destroy']);

    // Applications
    Route::post('/jobs/{job}/apply', [JobApplicationsController::class, 'apply']);
    Route::get('/applications', [JobApplicationsController::class, 'index']);
    Route::get('/applications/{application}', [JobApplicationsController::class, 'show']);
    Route::post('/applications/{application}/status', [JobApplicationsController::class, 'updateStatus']);

    // Interviews
 // routes/api.php
        Route::get('/interviews', [InterviewsController::class, 'index']);
        Route::post('/applications/{application}/interviews', [InterviewsController::class, 'store']);
        Route::put('/interviews/{interview}', [InterviewsController::class, 'update']);
        Route::post('/interviews/{interview}/cancel', [InterviewsController::class, 'cancel']);

    // Subscriptions / Payments
    Route::get('/subscriptions', [SubscriptionsController::class, 'index']);
    Route::post('/subscriptions', [SubscriptionsController::class, 'store']);
    Route::post('/subscriptions/{subscription}/cancel', [SubscriptionsController::class, 'cancel']);

    Route::get('/payments', [PaymentsController::class, 'index']);
    Route::post('/payments', [PaymentsController::class, 'store']);

    // Verification requests
    Route::get('/verification-requests', [VerificationRequestsController::class, 'index']);
    Route::post('/verification-requests', [VerificationRequestsController::class, 'store']);
    Route::post('/verification-requests/{verificationRequest}/review', [VerificationRequestsController::class, 'review']);

        Route::get('/conversations', [MessagesController::class, 'index']);
    Route::post('/conversations', [MessagesController::class, 'store']);
    Route::get('/conversations/{conversation}', [MessagesController::class, 'show']);
    Route::post('/conversations/{conversation}/messages', [MessagesController::class, 'send']);
    Route::post('/conversations/{conversation}/read', [MessagesController::class, 'markRead']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/users', [UsersController::class, 'index']);
    });
    // AI screenings (read-only)
    Route::get('/ai-screenings', [AiScreeningsController::class, 'index']);

    // Audit logs (admin-only)
    Route::get('/audit-logs', [AuditLogsController::class, 'index']);
});




Route::get('/debug/whoami', function (\Illuminate\Http\Request $request) {
    $u = $request->user();
    return response()->json([
        'id' => $u?->id,
        'role' => $u?->role,
        'email' => $u?->email,
        'x_user_id' => $request->header('X-User-Id'),
    ]);
});
