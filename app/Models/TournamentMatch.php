<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentMatch extends Model
{
    protected $fillable = [
        'tournament_id',
        'round',
        'bracket',
        'match_number',
        'slot_a_user_id',
        'slot_b_user_id',
        'winner_user_id',
        'score_a',
        'score_b',
        'scheduled_at',
        'completed_at',
        'status'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * The match statuses.
     */
    const STATUS_PENDING = 'pending';
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * The bracket types.
     */
    const BRACKET_WINNERS = 'winners';
    const BRACKET_LOSERS = 'losers';
    const BRACKET_FINALS = 'finals';

    /**
     * Get the tournament that owns the match.
     */
    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * Get the user in slot A.
     */
    public function slotA()
    {
        return $this->belongsTo(User::class, 'slot_a_user_id');
    }

    /**
     * Get the user in slot B.
     */
    public function slotB()
    {
        return $this->belongsTo(User::class, 'slot_b_user_id');
    }

    /**
     * Get the winner of the match.
     */
    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_user_id');
    }
}
