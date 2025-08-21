<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\Deposit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    /**
     * Initiate a payment transaction
     */
    public function initiate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:10',
            'payment_method' => 'required|string|in:bkash,nagad,rocket,card',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $amount = $request->amount;
        $paymentMethod = $request->payment_method;
        $transactionId = 'FFF-' . Str::random(8) . '-' . time();

        // Create a pending deposit record
        $deposit = Deposit::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'transaction_id' => $transactionId,
            'status' => 'pending',
        ]);

        // Prepare data for Uddokta payment gateway
        $paymentData = [
            'amount' => $amount,
            'currency' => 'BDT',
            'transaction_id' => $transactionId,
            'payment_method' => $paymentMethod,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'customer_phone' => $user->phone ?? '',
            'success_url' => config('app.url') . '/api/payments/verify?transaction_id=' . $transactionId,
            'cancel_url' => config('app.url') . '/payment/cancel',
            'webhook_url' => config('app.url') . '/api/payments/webhook',
        ];

        // Call Uddokta payment gateway API (mock implementation)
        try {
            // In a real implementation, this would be an actual API call
            // $response = Http::withHeaders([
            //     'Authorization' => 'Bearer ' . config('services.uddokta.api_key'),
            //     'Content-Type' => 'application/json',
            // ])->post(config('services.uddokta.api_url') . '/payment/initiate', $paymentData);
            
            // Mock successful response
            $gatewayResponse = [
                'success' => true,
                'payment_url' => 'https://uddokta-pay.example.com/pay/' . $transactionId,
                'transaction_id' => $transactionId,
            ];

            // Update deposit with gateway reference
            $deposit->update([
                'gateway_reference' => $transactionId,
                'gateway_data' => json_encode($gatewayResponse),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment initiated successfully',
                'payment_url' => $gatewayResponse['payment_url'],
                'transaction_id' => $transactionId,
            ]);

        } catch (\Exception $e) {
            // Update deposit status to failed
            $deposit->update(['status' => 'failed']);

            return response()->json([
                'success' => false,
                'message' => 'Payment initiation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify a payment transaction
     */
    public function verify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|string|exists:deposits,transaction_id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $transactionId = $request->transaction_id;
        $deposit = Deposit::where('transaction_id', $transactionId)->first();

        if (!$deposit) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found',
            ], 404);
        }

        // If already processed, return current status
        if ($deposit->status === 'completed') {
            return response()->json([
                'success' => true,
                'message' => 'Payment already processed',
                'deposit' => $deposit,
            ]);
        }

        // Verify with payment gateway (mock implementation)
        try {
            // In a real implementation, this would be an actual API call
            // $response = Http::withHeaders([
            //     'Authorization' => 'Bearer ' . config('services.uddokta.api_key'),
            //     'Content-Type' => 'application/json',
            // ])->get(config('services.uddokta.api_url') . '/payment/verify/' . $transactionId);
            
            // Mock successful verification
            $verificationResponse = [
                'success' => true,
                'transaction_id' => $transactionId,
                'status' => 'completed',
                'amount' => $deposit->amount,
                'payment_method' => $deposit->payment_method,
                'paid_at' => now()->toIsoString(),
            ];

            // Process successful payment
            if ($verificationResponse['status'] === 'completed') {
                // Update deposit status
                $deposit->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'gateway_data' => json_encode($verificationResponse),
                ]);

                // Create transaction record
                $transaction = Transaction::create([
                    'user_id' => $deposit->user_id,
                    'amount' => $deposit->amount,
                    'type' => 'deposit',
                    'status' => 'completed',
                    'description' => 'Deposit via ' . ucfirst($deposit->payment_method),
                    'reference_id' => $deposit->id,
                    'reference_type' => 'deposit',
                ]);

                // Update user wallet
                $wallet = Wallet::where('user_id', $deposit->user_id)->first();
                $wallet->update([
                    'balance' => $wallet->balance + $deposit->amount,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment verified and processed successfully',
                    'deposit' => $deposit,
                    'transaction' => $transaction,
                    'new_balance' => $wallet->balance,
                ]);
            } else {
                // Payment failed or pending at gateway
                $deposit->update([
                    'status' => $verificationResponse['status'],
                    'gateway_data' => json_encode($verificationResponse),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Payment verification failed: ' . $verificationResponse['status'],
                    'deposit' => $deposit,
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment verification error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle payment gateway webhook
     */
    public function webhook(Request $request)
    {
        // Verify webhook signature (in a real implementation)
        // $signature = $request->header('X-Uddokta-Signature');
        // if (!$this->verifyWebhookSignature($signature, $request->getContent())) {
        //     return response()->json(['error' => 'Invalid signature'], 403);
        // }

        $payload = $request->all();
        $transactionId = $payload['transaction_id'] ?? null;

        if (!$transactionId) {
            return response()->json(['error' => 'Missing transaction ID'], 400);
        }

        $deposit = Deposit::where('transaction_id', $transactionId)->first();

        if (!$deposit) {
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        // Process webhook based on event type
        $eventType = $payload['event'] ?? '';

        switch ($eventType) {
            case 'payment.completed':
                // Similar logic to verify method, but triggered by webhook
                if ($deposit->status !== 'completed') {
                    // Update deposit status
                    $deposit->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                        'gateway_data' => json_encode($payload),
                    ]);

                    // Create transaction record
                    $transaction = Transaction::create([
                        'user_id' => $deposit->user_id,
                        'amount' => $deposit->amount,
                        'type' => 'deposit',
                        'status' => 'completed',
                        'description' => 'Deposit via ' . ucfirst($deposit->payment_method),
                        'reference_id' => $deposit->id,
                        'reference_type' => 'deposit',
                    ]);

                    // Update user wallet
                    $wallet = Wallet::where('user_id', $deposit->user_id)->first();
                    $wallet->update([
                        'balance' => $wallet->balance + $deposit->amount,
                    ]);
                }
                break;

            case 'payment.failed':
                $deposit->update([
                    'status' => 'failed',
                    'gateway_data' => json_encode($payload),
                ]);
                break;

            case 'payment.cancelled':
                $deposit->update([
                    'status' => 'cancelled',
                    'gateway_data' => json_encode($payload),
                ]);
                break;

            default:
                // Unknown event type
                return response()->json(['error' => 'Unknown event type'], 400);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Get user's deposit history
     */
    public function history(Request $request)
    {
        $user = Auth::user();
        $perPage = $request->per_page ?? 15;
        
        $deposits = Deposit::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
            
        return response()->json($deposits);
    }
}
