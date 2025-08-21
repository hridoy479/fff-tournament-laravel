<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'status',
        'meta',
        'transactionable_type',
        'transactionable_id'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'meta' => 'json'
    ];

    /**
     * The transaction types.
     */
    const TYPE_DEPOSIT = 'deposit';
    const TYPE_WITHDRAWAL = 'withdrawal';
    const TYPE_TOURNAMENT_ENTRY = 'tournament_entry';
    const TYPE_TOURNAMENT_PRIZE = 'tournament_prize';

    /**
     * The transaction statuses.
     */
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Get the user that owns the transaction.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent transactionable model.
     */
    public function transactionable()
    {
        return $this->morphTo();
    }
}
