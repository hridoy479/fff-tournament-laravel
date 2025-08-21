<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    /**
     * Get the authenticated user's wallet balance
     */
    public function balance()
    {
        $user = Auth::user();
        $wallet = Wallet::where('user_id', $user->id)->first();
        
        if (!$wallet) {
            // Create wallet if it doesn't exist
            $wallet = Wallet::create([
                'user_id' => $user->id,
                'balance' => 0
            ]);
        }
        
        return response()->json([
            'success' => true,
            'balance' => $wallet->balance,
            'on_hold' => $wallet->on_hold ?? 0,
            'available' => $wallet->balance - ($wallet->on_hold ?? 0),
            'currency' => 'BDT'
        ]);
    }

    /**
     * Get the authenticated user's transaction history
     */
    public function transactions(Request $request)
    {
        $user = Auth::user();
        $perPage = $request->per_page ?? 15;
        $type = $request->type; // Optional filter by transaction type
        
        $query = Transaction::where('user_id', $user->id);
        
        // Filter by transaction type if provided
        if ($type) {
            $query->where('type', $type);
        }
        
        $transactions = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);
            
        return response()->json($transactions);
    }

    /**
     * Get a summary of the authenticated user's wallet activity
     */
    public function summary()
    {
        $user = Auth::user();
        
        // Get wallet
        $wallet = Wallet::where('user_id', $user->id)->first();
        
        if (!$wallet) {
            return response()->json([
                'success' => false,
                'message' => 'Wallet not found',
            ], 404);
        }
        
        // Get deposit stats
        $depositStats = Deposit::where('user_id', $user->id)
            ->select(
                DB::raw('COUNT(*) as total_count'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN amount ELSE 0 END) as total_completed'),
                DB::raw('COUNT(CASE WHEN status = "pending" THEN 1 ELSE NULL END) as pending_count')
            )
            ->first();
            
        // Get withdrawal stats
        $withdrawalStats = Withdrawal::where('user_id', $user->id)
            ->select(
                DB::raw('COUNT(*) as total_count'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN amount ELSE 0 END) as total_completed'),
                DB::raw('COUNT(CASE WHEN status = "pending" THEN 1 ELSE NULL END) as pending_count')
            )
            ->first();
            
        // Get recent transactions
        $recentTransactions = Transaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();
            
        return response()->json([
            'success' => true,
            'wallet' => [
                'balance' => $wallet->balance,
                'on_hold' => $wallet->on_hold ?? 0,
                'available' => $wallet->balance - ($wallet->on_hold ?? 0),
                'currency' => 'BDT'
            ],
            'deposits' => [
                'total_count' => $depositStats->total_count,
                'total_amount' => $depositStats->total_completed,
                'pending_count' => $depositStats->pending_count
            ],
            'withdrawals' => [
                'total_count' => $withdrawalStats->total_count,
                'total_amount' => $withdrawalStats->total_completed,
                'pending_count' => $withdrawalStats->pending_count
            ],
            'recent_transactions' => $recentTransactions
        ]);
    }

    /**
     * Admin only: Get all users' wallet balances
     */
    public function adminWallets(Request $request)
    {
        $user = Auth::user();
        
        // Check if user is admin
        if (!$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }
        
        $perPage = $request->per_page ?? 15;
        
        $wallets = Wallet::with('user')
            ->orderBy('balance', 'desc')
            ->paginate($perPage);
            
        return response()->json($wallets);
    }
}
