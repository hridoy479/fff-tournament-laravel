<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentEntry extends Model
{
    protected $fillable = [
        'tournament_id',
        'user_id',
        'status',
        'joined_at'
    ];

    protected $casts = [
        'joined_at' => 'datetime',
    ];

    /**
     * The entry statuses.
     */
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    /**
     * @deprecated
     */
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Get the tournament that owns the entry.
     */
    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * Get the user that owns the entry.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
