<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
    /**
     * Get transaction details
     */
    public function show(string $id)
    {
        $user = Auth::user();
        $transaction = Transaction::findOrFail($id);
        
        // Check if the transaction belongs to the authenticated user or if user is admin
        if ($transaction->user_id !== $user->id && !$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }
        
        // Load related models based on transaction type
        if ($transaction->reference_type) {
            $transaction->load($transaction->reference_type);
        }
        
        return response()->json([
            'success' => true,
            'transaction' => $transaction
        ]);
    }

    /**
     * Admin only: Get all transactions with filtering options
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
            'type' => 'nullable|string|in:deposit,withdrawal,tournament_entry,tournament_prize',
            'status' => 'nullable|string|in:pending,completed,failed,cancelled',
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
        $query = Transaction::with('user');
        
        // Apply filters
        if ($request->type) {
            $query->where('type', $request->type);
        }
        
        if ($request->status) {
            $query->where('status', $request->status);
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
        
        $transactions = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);
            
        return response()->json($transactions);
    }

    /**
     * Admin only: Update transaction status
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
        
        $transaction = Transaction::findOrFail($id);
        
        // Update transaction status
        $transaction->update([
            'status' => $request->status,
            'admin_notes' => $request->notes,
            'updated_by' => $user->id,
        ]);
        
        // In a real implementation, we would handle side effects based on transaction type
        // For example, updating wallet balance, deposit status, withdrawal status, etc.
        
        return response()->json([
            'success' => true,
            'message' => 'Transaction status updated successfully',
            'transaction' => $transaction
        ]);
    }
}
