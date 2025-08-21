<?php
// routes/api.php
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\Api\TournamentController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\WithdrawalController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\StatisticsController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\DepositController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Auth\SocialAuthController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/auth/{provider}', [SocialAuthController::class, 'redirect']);
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // User profile routes
    Route::get('/profile', [UserController::class, 'profile']);
    Route::put('/profile', [UserController::class, 'updateProfile']);
    Route::post('/profile/change-password', [UserController::class, 'changePassword']);
    Route::get('/profile/tournaments', [UserController::class, 'tournamentHistory']);
    Route::get('/profile/matches', [UserController::class, 'matchHistory']);
    
    Route::apiResource('tournaments', TournamentController::class)->except(['index', 'show']);
    Route::post('/tournaments/{id}/register', [TournamentController::class, 'registerUser']);
    Route::post('/tournaments/{id}/cancel', [TournamentController::class, 'cancelTournament']);
    Route::post('/tournaments/{id}/brackets', [TournamentController::class, 'generateBrackets']);
    Route::get('/tournaments/{id}/progress', [TournamentController::class, 'trackProgress']);
    Route::post('/tournaments/{id}/distribute-prizes', [TournamentController::class, 'distributePrizes']);
    
    Route::apiResource('teams', TeamController::class);
    Route::post('/teams/{id}/invite', [TeamController::class, 'invitePlayer']);
    Route::post('/teams/join', [TeamController::class, 'joinTeam']);
    Route::delete('/teams/{id}/members/{userId}', [TeamController::class, 'removeMember']);
    
    Route::apiResource('matches', MatchController::class)->only(['update']);
    Route::post('/matches/{match}/report', [MatchController::class, 'reportScore']);
    Route::post('/matches/{match}/reschedule', [MatchController::class, 'rescheduleMatch']);
    
    // Payment routes
Route::post('/payments/initiate', [PaymentController::class, 'initiate']);
Route::get('/payments/verify', [PaymentController::class, 'verify']);
Route::get('/payments/history', [PaymentController::class, 'history']);

// Deposit routes
Route::get('/deposits', [DepositController::class, 'index']);
Route::post('/deposits', [DepositController::class, 'store']);
Route::get('/deposits/{id}', [DepositController::class, 'show']);
Route::post('/deposits/verify', [DepositController::class, 'verify']);
Route::get('/admin/deposits', [DepositController::class, 'adminIndex']);
Route::put('/admin/deposits/{id}/status', [DepositController::class, 'updateStatus']);
    
    // Withdrawal routes
Route::post('/withdrawals/request', [WithdrawalController::class, 'request']);
Route::get('/withdrawals/history', [WithdrawalController::class, 'history']);
Route::post('/withdrawals/{id}/cancel', [WithdrawalController::class, 'cancel']);
Route::post('/withdrawals/{id}/process', [WithdrawalController::class, 'process']);
Route::get('/admin/withdrawals', [WithdrawalController::class, 'adminList']);

// Notification routes
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
        Route::delete('/read/all', [NotificationController::class, 'deleteAllRead']);
    });
});

// Chat routes
Route::get('/matches/{matchId}/chat', [ChatController::class, 'matchChat']);
Route::get('/tournaments/{tournamentId}/chat', [ChatController::class, 'tournamentChat']);
Route::post('/chat/send', [ChatController::class, 'sendMessage']);
Route::delete('/chat/{id}', [ChatController::class, 'destroy']);
Route::post('/chat/{id}/report', [ChatController::class, 'reportMessage']);

// Banner routes (admin only)
Route::get('/admin/banners', [BannerController::class, 'adminIndex']);
Route::post('/banners', [BannerController::class, 'store']);
Route::get('/banners/{id}', [BannerController::class, 'show']);
Route::put('/banners/{id}', [BannerController::class, 'update']);
Route::delete('/banners/{id}', [BannerController::class, 'destroy']);
Route::post('/banners/{id}/toggle', [BannerController::class, 'toggleStatus']);

// Game routes (admin only)
Route::get('/admin/games', [GameController::class, 'adminIndex']);
Route::post('/games', [GameController::class, 'store']);
Route::put('/games/{id}', [GameController::class, 'update']);
Route::delete('/games/{id}', [GameController::class, 'destroy']);
Route::post('/games/{id}/toggle', [GameController::class, 'toggleStatus']);

// User management routes (admin only)
Route::get('/admin/users', [UserController::class, 'index']);
Route::put('/admin/users/{id}/role', [UserController::class, 'updateRole']);
Route::post('/admin/users/{id}/toggle-ban', [UserController::class, 'toggleBan']);

// Settings routes
Route::get('/admin/settings', [SettingsController::class, 'index']);
Route::put('/admin/settings', [SettingsController::class, 'update']);
Route::get('/settings/notifications', [SettingsController::class, 'getUserNotificationSettings']);
Route::put('/settings/notifications', [SettingsController::class, 'updateUserNotificationSettings']);

// Statistics routes
Route::get('/admin/statistics', [StatisticsController::class, 'adminDashboard']);
Route::get('/statistics/user', [StatisticsController::class, 'userStats']);

// Wallet routes
Route::get('/wallet/balance', [WalletController::class, 'balance']);
Route::get('/wallet/transactions', [WalletController::class, 'transactions']);
Route::get('/wallet/summary', [WalletController::class, 'summary']);
Route::get('/admin/wallets', [WalletController::class, 'adminWallets']);

// Transaction routes
Route::get('/transactions/{id}', [TransactionController::class, 'show']);
Route::get('/admin/transactions', [TransactionController::class, 'adminIndex']);
Route::put('/admin/transactions/{id}/status', [TransactionController::class, 'updateStatus']);
});

// Public routes
Route::apiResource('tournaments', TournamentController::class)->only(['index', 'show']);
Route::get('/statistics/public', [StatisticsController::class, 'publicStats']);
Route::get('/games', [GameController::class, 'index']);
Route::get('/games/categories', [GameController::class, 'categories']);
Route::get('/games/{id}', [GameController::class, 'show']);
Route::get('/banners', [BannerController::class, 'index']);
Route::get('/users/{id}', [UserController::class, 'show']);
Route::get('/settings/public', [SettingsController::class, 'publicSettings']);
Route::post('/payments/webhook', [PaymentController::class, 'webhook']);

// Tournament routes
Route::prefix('tournaments')->group(function () {
    Route::get('{id}/bracket', [TournamentController::class, 'getBracketData']);
});