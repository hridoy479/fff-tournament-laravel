<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Deposit extends Model
{
    protected $fillable = [
        'user_id',
        'gateway',
        'amount',
        'status',
        'trx_id',
        'uddokta_ref',
        'callback_payload'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'callback_payload' => 'json'
    ];

    /**
     * The deposit statuses.
     */
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Get the user that owns the deposit.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the transaction associated with the deposit.
     */
    public function transaction()
    {
        return $this->morphOne(Transaction::class, 'transactionable');
    }
}
