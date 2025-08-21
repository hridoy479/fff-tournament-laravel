<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class WithdrawalController extends Controller
{
    /**
     * Request a withdrawal
     */
    public function request(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:100',
            'payment_method' => 'required|string|in:bkash,nagad,rocket,bank',
            'account_details' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $amount = $request->amount;
        $paymentMethod = $request->payment_method;
        $accountDetails = $request->account_details;

        // Check if user has sufficient balance
        $wallet = Wallet::where('user_id', $user->id)->first();

        if (!$wallet || $wallet->balance < $amount) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance',
            ], 400);
        }

        // Create withdrawal request
        $withdrawal = Withdrawal::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'account_details' => $accountDetails,
            'status' => 'pending',
            'reference_id' => 'WD-' . Str::random(8) . '-' . time(),
        ]);

        // Deduct amount from wallet (put on hold)
        $wallet->update([
            'balance' => $wallet->balance - $amount,
            'on_hold' => $wallet->on_hold + $amount,
        ]);

        // Create transaction record
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'amount' => -$amount, // Negative amount for withdrawal
            'type' => 'withdrawal',
            'status' => 'pending',
            'description' => 'Withdrawal request via ' . ucfirst($paymentMethod),
            'reference_id' => $withdrawal->id,
            'reference_type' => 'withdrawal',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal request submitted successfully',
            'withdrawal' => $withdrawal,
            'transaction' => $transaction,
        ]);
    }

    /**
     * Get user's withdrawal history
     */
    public function history(Request $request)
    {
        $user = Auth::user();
        $perPage = $request->per_page ?? 15;
        
        $withdrawals = Withdrawal::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
            
        return response()->json($withdrawals);
    }

    /**
     * Cancel a pending withdrawal request
     */
    public function cancel(string $id)
    {
        $user = Auth::user();
        $withdrawal = Withdrawal::where('id', $id)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if (!$withdrawal) {
            return response()->json([
                'success' => false,
                'message' => 'Withdrawal request not found or cannot be cancelled',
            ], 404);
        }

        // Update withdrawal status
        $withdrawal->update(['status' => 'cancelled']);

        // Return amount to wallet
        $wallet = Wallet::where('user_id', $user->id)->first();
        $wallet->update([
            'balance' => $wallet->balance + $withdrawal->amount,
            'on_hold' => $wallet->on_hold - $withdrawal->amount,
        ]);

        // Update transaction status
        $transaction = Transaction::where('reference_id', $withdrawal->id)
            ->where('reference_type', 'withdrawal')
            ->first();
            
        if ($transaction) {
            $transaction->update(['status' => 'cancelled']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal request cancelled successfully',
            'withdrawal' => $withdrawal,
        ]);
    }

    /**
     * Admin: Process a withdrawal request
     */
    public function process(Request $request, string $id)
    {
        // Check if user is admin
        $user = Auth::user();
        if (!$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:completed,rejected',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $withdrawal = Withdrawal::where('id', $id)
            ->where('status', 'pending')
            ->first();

        if (!$withdrawal) {
            return response()->json([
                'success' => false,
                'message' => 'Withdrawal request not found or already processed',
            ], 404);
        }

        $status = $request->status;
        $notes = $request->notes;

        // Update withdrawal status
        $withdrawal->update([
            'status' => $status,
            'processed_by' => $user->id,
            'processed_at' => now(),
            'admin_notes' => $notes,
        ]);

        // Get user's wallet
        $wallet = Wallet::where('user_id', $withdrawal->user_id)->first();

        // Update transaction status
        $transaction = Transaction::where('reference_id', $withdrawal->id)
            ->where('reference_type', 'withdrawal')
            ->first();

        if ($status === 'completed') {
            // Update wallet on_hold amount
            $wallet->update([
                'on_hold' => $wallet->on_hold - $withdrawal->amount,
            ]);
            
            // Update transaction status
            if ($transaction) {
                $transaction->update(['status' => 'completed']);
            }
        } else if ($status === 'rejected') {
            // Return amount to wallet
            $wallet->update([
                'balance' => $wallet->balance + $withdrawal->amount,
                'on_hold' => $wallet->on_hold - $withdrawal->amount,
            ]);
            
            // Update transaction status
            if ($transaction) {
                $transaction->update(['status' => 'rejected']);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal request ' . $status,
            'withdrawal' => $withdrawal,
        ]);
    }

    /**
     * Admin: List all withdrawal requests
     */
    public function adminList(Request $request)
    {
        // Check if user is admin
        $user = Auth::user();
        if (!$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $status = $request->status;
        $perPage = $request->per_page ?? 15;
        
        $query = Withdrawal::with('user');
        
        if ($status) {
            $query->where('status', $status);
        }
        
        $withdrawals = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);
            
        return response()->json($withdrawals);
    }
}
