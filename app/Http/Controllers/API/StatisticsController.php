<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\Game;
use App\Models\Match;
use App\Models\Tournament;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StatisticsController extends Controller
{
    /**
     * Get dashboard statistics for admin
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function adminDashboard()
    {
        // Check if user is admin
        if (!auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Get basic counts
        $userCount = User::count();
        $tournamentCount = Tournament::count();
        $gameCount = Game::count();
        $matchCount = Match::count();

        // Get financial statistics
        $totalDeposits = Deposit::where('status', 'completed')->sum('amount');
        $totalWithdrawals = Withdrawal::where('status', 'completed')->sum('amount');
        $platformBalance = $totalDeposits - $totalWithdrawals;
        $platformFees = Tournament::sum('platform_fee');

        // Get user registration trend (last 7 days)
        $userTrend = $this->getUserRegistrationTrend();

        // Get tournament trend (last 7 days)
        $tournamentTrend = $this->getTournamentTrend();

        // Get top games by tournament count
        $topGames = $this->getTopGames();

        return response()->json([
            'user_count' => $userCount,
            'tournament_count' => $tournamentCount,
            'game_count' => $gameCount,
            'match_count' => $matchCount,
            'financial' => [
                'total_deposits' => $totalDeposits,
                'total_withdrawals' => $totalWithdrawals,
                'platform_balance' => $platformBalance,
                'platform_fees' => $platformFees,
            ],
            'user_trend' => $userTrend,
            'tournament_trend' => $tournamentTrend,
            'top_games' => $topGames,
        ]);
    }

    /**
     * Get public statistics for the platform
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function publicStats()
    {
        // Cache the statistics for 1 hour to improve performance
        return Cache::remember('public_statistics', 3600, function () {
            $userCount = User::count();
            $tournamentCount = Tournament::count();
            $gameCount = Game::where('is_active', true)->count();
            $matchCount = Match::count();
            
            // Get top games by tournament count (only active games)
            $topGames = $this->getTopGames(5, true);
            
            // Get recent tournaments (only active ones)
            $recentTournaments = Tournament::with('game')
                ->where('status', 'upcoming')
                ->orWhere('status', 'active')
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get()
                ->map(function ($tournament) {
                    return [
                        'id' => $tournament->id,
                        'name' => $tournament->name,
                        'game' => $tournament->game->name,
                        'prize_pool' => $tournament->prize_pool,
                        'start_date' => $tournament->start_date,
                        'entry_fee' => $tournament->entry_fee,
                        'participants_count' => $tournament->participants_count,
                    ];
                });

            return [
                'user_count' => $userCount,
                'tournament_count' => $tournamentCount,
                'game_count' => $gameCount,
                'match_count' => $matchCount,
                'top_games' => $topGames,
                'recent_tournaments' => $recentTournaments,
            ];
        });
    }

    /**
     * Get user statistics for the authenticated user
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function userStats()
    {
        $user = auth()->user();
        
        // Get user's wallet balance
        $wallet = Wallet::where('user_id', $user->id)->first();
        $balance = $wallet ? $wallet->balance : 0;
        
        // Get tournament participation stats
        $tournamentsJoined = $user->tournaments()->count();
        $tournamentsWon = $user->tournaments()->where('winner_id', $user->id)->count();
        
        // Get match statistics
        $matchesPlayed = $user->matches()->count();
        $matchesWon = $user->matches()->where('winner_id', $user->id)->count();
        $winRate = $matchesPlayed > 0 ? round(($matchesWon / $matchesPlayed) * 100, 2) : 0;
        
        // Get financial statistics
        $totalDeposits = Deposit::where('user_id', $user->id)
            ->where('status', 'completed')
            ->sum('amount');
        $totalWithdrawals = Withdrawal::where('user_id', $user->id)
            ->where('status', 'completed')
            ->sum('amount');
        
        // Get recent activity
        $recentActivity = $this->getUserRecentActivity($user->id);
        
        return response()->json([
            'balance' => $balance,
            'tournaments' => [
                'joined' => $tournamentsJoined,
                'won' => $tournamentsWon,
                'win_rate' => $tournamentsJoined > 0 ? round(($tournamentsWon / $tournamentsJoined) * 100, 2) : 0,
            ],
            'matches' => [
                'played' => $matchesPlayed,
                'won' => $matchesWon,
                'win_rate' => $winRate,
            ],
            'financial' => [
                'total_deposits' => $totalDeposits,
                'total_withdrawals' => $totalWithdrawals,
            ],
            'recent_activity' => $recentActivity,
        ]);
    }

    /**
     * Get user registration trend for the last 7 days
     * 
     * @return array
     */
    private function getUserRegistrationTrend()
    {
        $days = 7;
        $trend = [];
        
        for ($i = 0; $i < $days; $i++) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $count = User::whereDate('created_at', $date)->count();
            
            $trend[] = [
                'date' => $date,
                'count' => $count,
            ];
        }
        
        return array_reverse($trend);
    }

    /**
     * Get tournament trend for the last 7 days
     * 
     * @return array
     */
    private function getTournamentTrend()
    {
        $days = 7;
        $trend = [];
        
        for ($i = 0; $i < $days; $i++) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $count = Tournament::whereDate('created_at', $date)->count();
            
            $trend[] = [
                'date' => $date,
                'count' => $count,
            ];
        }
        
        return array_reverse($trend);
    }

    /**
     * Get top games by tournament count
     * 
     * @param int $limit
     * @param bool $activeOnly
     * @return array
     */
    private function getTopGames($limit = 5, $activeOnly = false)
    {
        $query = Game::select('games.id', 'games.name', DB::raw('COUNT(tournaments.id) as tournament_count'))
            ->leftJoin('tournaments', 'games.id', '=', 'tournaments.game_id')
            ->groupBy('games.id', 'games.name')
            ->orderBy('tournament_count', 'desc');
            
        if ($activeOnly) {
            $query->where('games.is_active', true);
        }
        
        return $query->take($limit)->get();
    }

    /**
     * Get user's recent activity
     * 
     * @param int $userId
     * @return array
     */
    private function getUserRecentActivity($userId)
    {
        // Get recent tournaments
        $tournaments = Tournament::whereHas('participants', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })
        ->orderBy('created_at', 'desc')
        ->take(5)
        ->get()
        ->map(function ($tournament) {
            return [
                'type' => 'tournament',
                'id' => $tournament->id,
                'name' => $tournament->name,
                'date' => $tournament->created_at,
                'details' => [
                    'status' => $tournament->status,
                    'prize_pool' => $tournament->prize_pool,
                ],
            ];
        });
        
        // Get recent matches
        $matches = Match::where(function ($query) use ($userId) {
            $query->whereHas('team1.members', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->orWhereHas('team2.members', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            });
        })
        ->orderBy('created_at', 'desc')
        ->take(5)
        ->get()
        ->map(function ($match) use ($userId) {
            $isWinner = $match->winner_id && 
                (($match->team1->members->contains('user_id', $userId) && $match->winner_id == $match->team1_id) ||
                ($match->team2->members->contains('user_id', $userId) && $match->winner_id == $match->team2_id));
            
            return [
                'type' => 'match',
                'id' => $match->id,
                'name' => $match->team1->name . ' vs ' . $match->team2->name,
                'date' => $match->created_at,
                'details' => [
                    'status' => $match->status,
                    'is_winner' => $isWinner,
                ],
            ];
        });
        
        // Get recent transactions
        $deposits = Deposit::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->take(3)
            ->get()
            ->map(function ($deposit) {
                return [
                    'type' => 'deposit',
                    'id' => $deposit->id,
                    'name' => 'Deposit #' . $deposit->id,
                    'date' => $deposit->created_at,
                    'details' => [
                        'amount' => $deposit->amount,
                        'status' => $deposit->status,
                    ],
                ];
            });
            
        $withdrawals = Withdrawal::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->take(3)
            ->get()
            ->map(function ($withdrawal) {
                return [
                    'type' => 'withdrawal',
                    'id' => $withdrawal->id,
                    'name' => 'Withdrawal #' . $withdrawal->id,
                    'date' => $withdrawal->created_at,
                    'details' => [
                        'amount' => $withdrawal->amount,
                        'status' => $withdrawal->status,
                    ],
                ];
            });
        
        // Combine all activities, sort by date, and take the most recent 10
        $allActivity = $tournaments->concat($matches)->concat($deposits)->concat($withdrawals)
            ->sortByDesc('date')
            ->values()
            ->take(10);
            
        return $allActivity;
    }
}
