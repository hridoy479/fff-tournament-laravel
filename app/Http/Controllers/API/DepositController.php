<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DepositController extends Controller
{
    /**
     * Get user's deposit history
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $perPage = $request->per_page ?? 15;
        
        $deposits = Deposit::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
            
        return response()->json($deposits);
    }

    /**
     * Initiate a new deposit
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:10',
            'gateway' => 'required|string|in:bkash,nagad,rocket,uddokta',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $amount = $request->amount;
        $gateway = $request->gateway;
        $trxId = 'DEP-' . Str::random(8) . '-' . time();

        // Create a pending deposit record
        $deposit = Deposit::create([
            'user_id' => $user->id,
            'gateway' => $gateway,
            'amount' => $amount,
            'status' => Deposit::STATUS_PENDING,
            'trx_id' => $trxId,
        ]);

        // Create a corresponding transaction record
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'type' => Transaction::TYPE_DEPOSIT,
            'amount' => $amount,
            'status' => Transaction::STATUS_PENDING,
            'meta' => [
                'gateway' => $gateway,
                'trx_id' => $trxId,
            ],
            'transactionable_type' => 'App\\Models\\Deposit',
            'transactionable_id' => $deposit->id,
        ]);

        // In a real implementation, we would integrate with the payment gateway here
        // For now, we'll just return the deposit details with a mock payment URL

        return response()->json([
            'success' => true,
            'message' => 'Deposit initiated successfully',
            'deposit' => $deposit,
            'payment_url' => 'https://payment-gateway.example.com/pay/' . $trxId,
            'transaction_id' => $trxId,
        ]);
    }

    /**
     * Get deposit details
     */
    public function show(string $id)
    {
        $user = Auth::user();
        $deposit = Deposit::findOrFail($id);
        
        // Check if the deposit belongs to the authenticated user or if user is admin
        if ($deposit->user_id !== $user->id && !$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }
        
        // Load the related transaction
        $deposit->load('transaction');
        
        return response()->json([
            'success' => true,
            'deposit' => $deposit
        ]);
    }

    /**
     * Verify a deposit (callback from payment gateway)
     */
    public function verify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trx_id' => 'required|string|exists:deposits,trx_id',
            'status' => 'required|string|in:completed,failed,cancelled',
            'gateway_reference' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $trxId = $request->trx_id;
        $status = $request->status;
        $gatewayReference = $request->gateway_reference;

        $deposit = Deposit::where('trx_id', $trxId)->first();

        if (!$deposit) {
            return response()->json([
                'success' => false,
                'message' => 'Deposit not found',
            ], 404);
        }

        // If already processed, return current status
        if ($deposit->status !== Deposit::STATUS_PENDING) {
            return response()->json([
                'success' => true,
                'message' => 'Deposit already processed',
                'deposit' => $deposit,
            ]);
        }

        // Process the deposit based on the status
        DB::beginTransaction();
        try {
            // Update deposit status
            $deposit->update([
                'status' => $status,
                'uddokta_ref' => $gatewayReference,
                'callback_payload' => $request->all(),
            ]);

            // Update the corresponding transaction
            $transaction = Transaction::where('transactionable_type', 'App\\Models\\Deposit')
                ->where('transactionable_id', $deposit->id)
                ->first();

            if ($transaction) {
                $transaction->update([
                    'status' => $status,
                    'meta' => array_merge($transaction->meta ?? [], [
                        'gateway_reference' => $gatewayReference,
                        'callback_data' => $request->all(),
                    ]),
                ]);
            }

            // If deposit is completed, update user's wallet balance
            if ($status === Deposit::STATUS_COMPLETED) {
                $wallet = Wallet::firstOrCreate(
                    ['user_id' => $deposit->user_id],
                    ['balance' => 0]
                );

                $wallet->increment('balance', $deposit->amount);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Deposit ' . $status,
                'deposit' => $deposit->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to process deposit: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Admin only: Get all deposits with filtering options
     */
    public function adminIndex(Request $request)
    {
        $user = Auth::user();
        
        // Check if user is admin
        if (!$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|string|in:pending,completed,failed,cancelled',
            'gateway' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'min_amount' => 'nullable|numeric',
            'max_amount' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $perPage = $request->per_page ?? 15;
        $query = Deposit::with('user');
        
        // Apply filters
        if ($request->status) {
            $query->where('status', $request->status);
        }
        
        if ($request->gateway) {
            $query->where('gateway', $request->gateway);
        }
        
        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }
        
        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        
        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        
        if ($request->min_amount) {
            $query->where('amount', '>=', $request->min_amount);
        }
        
        if ($request->max_amount) {
            $query->where('amount', '<=', $request->max_amount);
        }
        
        $deposits = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);
            
        return response()->json($deposits);
    }

    /**
     * Admin only: Update deposit status manually
     */
    public function updateStatus(Request $request, string $id)
    {
        $user = Auth::user();
        
        // Check if user is admin
        if (!$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:pending,completed,failed,cancelled',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $deposit = Deposit::findOrFail($id);
        $newStatus = $request->status;
        $oldStatus = $deposit->status;
        
        // Process the status update
        DB::beginTransaction();
        try {
            // Update deposit status
            $deposit->update([
                'status' => $newStatus,
                'callback_payload' => array_merge($deposit->callback_payload ?? [], [
                    'admin_notes' => $request->notes,
                    'updated_by' => $user->id,
                    'updated_at' => now(),
                ]),
            ]);

            // Update the corresponding transaction
            $transaction = Transaction::where('transactionable_type', 'App\\Models\\Deposit')
                ->where('transactionable_id', $deposit->id)
                ->first();

            if ($transaction) {
                $transaction->update([
                    'status' => $newStatus,
                    'meta' => array_merge($transaction->meta ?? [], [
                        'admin_notes' => $request->notes,
                        'updated_by' => $user->id,
                        'updated_at' => now(),
                    ]),
                ]);
            }

            // Handle wallet balance updates based on status changes
            $wallet = Wallet::firstOrCreate(
                ['user_id' => $deposit->user_id],
                ['balance' => 0]
            );

            // If changing from non-completed to completed, add to balance
            if ($oldStatus !== Deposit::STATUS_COMPLETED && $newStatus === Deposit::STATUS_COMPLETED) {
                $wallet->increment('balance', $deposit->amount);
            }
            // If changing from completed to non-completed, subtract from balance
            else if ($oldStatus === Deposit::STATUS_COMPLETED && $newStatus !== Deposit::STATUS_COMPLETED) {
                // Only deduct if there's enough balance
                if ($wallet->balance >= $deposit->amount) {
                    $wallet->decrement('balance', $deposit->amount);
                } else {
                    throw new \Exception('Insufficient wallet balance to reverse this deposit');
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Deposit status updated successfully',
                'deposit' => $deposit->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update deposit status: ' . $e->getMessage(),
            ], 500);
        }
    }
}
