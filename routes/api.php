<?php

use App\Http\Controllers\Api\v1\AuthController;
use App\Http\Controllers\Api\v1\Buyer\BuyerCatalogController;
use App\Http\Controllers\Api\v1\Buyer\BuyerCheckoutController;
use App\Http\Controllers\Api\v1\Buyer\BuyerDashboardController;
use App\Http\Controllers\Api\v1\Buyer\BuyerNotificationController;
use App\Http\Controllers\Api\v1\Buyer\BuyerOrderController;
use App\Http\Controllers\Api\v1\Buyer\BuyerPortController;
use App\Http\Controllers\Api\v1\Buyer\BuyerProfileController;
use App\Http\Controllers\Api\v1\ExporterBlockchainController;
use App\Http\Controllers\Api\v1\ExporterController;
use App\Http\Controllers\Api\v1\ExporterOrderController;
use App\Http\Controllers\Api\v1\Farmer\FarmerBatchController;
use App\Http\Controllers\Api\v1\Farmer\FarmerBatchPhotoController;
use App\Http\Controllers\Api\v1\Farmer\FarmerBatchSurveyController;
use App\Http\Controllers\Api\v1\Farmer\FarmerDashboardController;
use App\Http\Controllers\Api\v1\Farmer\FarmerDeviceController;
use App\Http\Controllers\Api\v1\Farmer\FarmerNotificationController;
use App\Http\Controllers\Api\v1\Farmer\FarmerProfileController;
use App\Http\Controllers\Api\v1\Farmer\FarmerSyncController;
use App\Http\Controllers\Api\v1\ProfileController;
use App\Http\Controllers\Api\v1\TelemetryController;
use App\Http\Controllers\HashController;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────────────────────────────────
//  HASH – Generate hash kontrak (PDF) & IoT sensor sekaligus
//  Sumber: ExporterController::generateCertificate() + FarmerBatchController::logs()
// ─────────────────────────────────────────────────────────────────────────
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::post('/hashes/generate', [HashController::class, 'generate']);
});

Route::prefix('v1')->group(function () {

    Route::prefix('auth')->group(function () {
        // Public
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);

        // Protected Auth
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/refresh', [AuthController::class, 'refresh']);
        });
    });

    Route::middleware(['auth:sanctum', 'role:buyer'])->prefix('buyer')->group(function () {
        // Dashboard
        Route::get('/dashboard', [BuyerDashboardController::class, 'dashboard']);

        // Catalog
        Route::prefix('catalog')->group(function () {
            Route::get('/', [BuyerCatalogController::class, 'index']);
            Route::get('/{id}', [BuyerCatalogController::class, 'show']);
            Route::get('/{id}/logs', [BuyerCatalogController::class, 'logs']);
            Route::get('/{id}/logs/trend', [BuyerCatalogController::class, 'trend']);
            Route::get('/{id}/snapshots', [BuyerCatalogController::class, 'snapshots']);
        });

        // Checkout
        Route::post('/checkout', [BuyerCheckoutController::class, 'store']);

        // Orders
        Route::get('/orders', [BuyerOrderController::class, 'index']);
        Route::get('/orders/{id}', [BuyerOrderController::class, 'show']);
        Route::post('/orders/{id}/payment/confirm', [BuyerOrderController::class, 'confirmPayment']);

        // Ports
        Route::get('/ports', [BuyerPortController::class, 'index']);

        // Notifications
        Route::prefix('notifications')->group(function () {
            Route::get('/', [BuyerNotificationController::class, 'index']);
            Route::get('/unread-count', [BuyerNotificationController::class, 'unreadCount']);
            Route::patch('/{id}/read', [BuyerNotificationController::class, 'markAsRead']);
            // Explicitly reject non-PATCH methods on /{id}/read with 404
            Route::match(['get', 'post', 'put', 'delete'], '/{id}/read', function () {
                return response()->json([
                    'success' => false,
                    'code' => 'NOT_FOUND',
                    'message' => 'Endpoint tidak ditemukan',
                    'data' => null,
                    'timestamp' => now()->toIso8601String(),
                ], 404);
            });
        });

        // Profile, Preferences & Security
        Route::get('/profile', [BuyerProfileController::class, 'show']);
        Route::patch('/profile', [BuyerProfileController::class, 'update']);
        Route::patch('/security/password', [BuyerProfileController::class, 'changePassword']);
        Route::get('/preferences', [BuyerProfileController::class, 'preferences']);
        Route::patch('/preferences', [BuyerProfileController::class, 'updatePreferences']);
    });

    Route::middleware('auth:sanctum')->prefix('me')->group(function () {
        Route::patch('/security/password', [AuthController::class, 'changePassword']);
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::patch('/profile', [ProfileController::class, 'update']);
        Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);
        Route::get('/settings', [ProfileController::class, 'getSettings']);
        Route::patch('/settings', [ProfileController::class, 'updateSettings']);
        Route::get('/devices', [ProfileController::class, 'devices']);
    });

    // Wilayah Eksportir (Exporter)
    Route::middleware(['auth:sanctum', 'role:exporter'])->prefix('exporter')->group(function () {

        // Dashboard
        Route::get('/dashboard', [ExporterController::class, 'dashboard']);

        // Orders
        Route::get('/orders', [ExporterOrderController::class, 'index']);
        Route::get('/orders/{id}', [ExporterOrderController::class, 'show']);
        Route::post('/orders/{id}/confirm', [ExporterOrderController::class, 'confirm']);

        // Blockchain
        Route::get('/blockchain-activity', [ExporterController::class, 'blockchainActivity']);
        Route::get('/blockchain-failure-logs', [ExporterController::class, 'listBlockchainLogs']);

        Route::prefix('blockchain-logs')->group(function () {
            Route::get('/', [ExporterController::class, 'listBlockchainLogs']);
            Route::post('/{id}/retry', [ExporterController::class, 'retryBlockchainLog']);
        });

        // Manajemen Batch (Isolasi Data)
        Route::prefix('batches')->group(function () {
            Route::get('/available', [ExporterController::class, 'availableBatches']);
            Route::get('/available/{batchId}', [ExporterController::class, 'showAvailableBatch']);
            Route::get('/mine', [ExporterController::class, 'myBatches']);
            Route::get('/{id}', [ExporterController::class, 'showBatch']);
            Route::patch('/{id}', [ExporterController::class, 'updateBatch']);
            Route::post('/{id}/acquire', [ExporterController::class, 'acquire']);
            Route::post('/{id}/release', [ExporterController::class, 'releaseBatch']);
            Route::post('/{id}/certificate/generate', [ExporterController::class, 'generateCertificate']);
            Route::post('/{id}/certificate/publish', [ExporterController::class, 'publishCertificate']);
            Route::get('/{id}/certificate/pdf', [ExporterController::class, 'downloadCertificate']);
            Route::get('/{id}/telemetry', [TelemetryController::class, 'getTelemetry']);
        });

        Route::prefix('network')->group(function () {
            Route::get('/status', [ExporterBlockchainController::class, 'getNetworkStatus']);
        });

    });

    // Wilayah Petani (Farmer) — consolidated into single group
    Route::middleware(['auth:sanctum', 'role:farmer'])->prefix('farmer')->group(function () {
        // Dashboard
        Route::get('/dashboard', [FarmerDashboardController::class, 'dashboard']);

        // Profile, Preferences & Security
        Route::get('/profile', [FarmerProfileController::class, 'show']);
        Route::patch('/profile', [FarmerProfileController::class, 'update']);
        Route::post('/profile/avatar', [FarmerProfileController::class, 'uploadAvatar']);
        Route::get('/preferences', [FarmerProfileController::class, 'preferences']);
        Route::patch('/preferences', [FarmerProfileController::class, 'updatePreferences']);
        Route::patch('/security/password', [FarmerProfileController::class, 'changePassword']);

        // Batches
        Route::get('/batches', [FarmerBatchController::class, 'index']);
        Route::post('/batches', [FarmerBatchController::class, 'store']);
        Route::get('/batches/{id}', [FarmerBatchController::class, 'show']);
        Route::patch('/batches/{id}', [FarmerBatchController::class, 'update']);
        Route::delete('/batches/{id}', [FarmerBatchController::class, 'destroy']);
        Route::get('/batches/{id}/logs', [FarmerBatchController::class, 'logs']);
        Route::get('/batches/{id}/logs/trend', [FarmerBatchController::class, 'logTrend']);
        Route::patch('/batches/{id}/status', [FarmerBatchController::class, 'updateStatus']);
        Route::get('/batches/{id}/telemetry', [TelemetryController::class, 'getTelemetry']);

        // Batch Photos
        Route::post('/batches/{id}/photos', [FarmerBatchPhotoController::class, 'store']);
        Route::delete('/batches/{id}/photos/{photoId}', [FarmerBatchPhotoController::class, 'destroy']);
        Route::get('/batches/{id}/photos', [FarmerBatchPhotoController::class, 'index']);

        // Batch Survey
        Route::get('/batches/{id}/survey-status', [FarmerBatchSurveyController::class, 'show']);
        Route::post('/batches/{id}/submit-survey', [FarmerBatchSurveyController::class, 'submit']);
        Route::post('/batches/{id}/cancel-survey', [FarmerBatchSurveyController::class, 'cancel']);

        // Devices
        Route::get('/devices', [FarmerDeviceController::class, 'index']);
        Route::delete('/devices/{deviceId}', [FarmerDeviceController::class, 'destroy']);
        Route::get('/connection-status', [FarmerDeviceController::class, 'connectionStatus']);

        // Notifications
        Route::get('/notifications', [FarmerNotificationController::class, 'index']);
        Route::patch('/notifications/{id}/read', [FarmerNotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [FarmerNotificationController::class, 'markAllAsRead']);
        Route::get('/notifications/unread-count', [FarmerNotificationController::class, 'unreadCount']);

        // Sync
        Route::post('/sync', [FarmerSyncController::class, 'sync']);
    });
});
