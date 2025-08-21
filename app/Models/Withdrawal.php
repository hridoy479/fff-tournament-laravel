<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Withdrawal extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'method',
        'account_no',
        'status',
        'reviewed_by',
        'note'
    ];

    protected $casts = [
        'amount' => 'decimal:2'
    ];

    /**
     * The withdrawal statuses.
     */
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_COMPLETED = 'completed';

    /**
     * Get the user that owns the withdrawal.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the admin that reviewed the withdrawal.
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get the transaction associated with the withdrawal.
     */
    public function transaction()
    {
        return $this->morphOne(Transaction::class, 'transactionable');
    }
}
