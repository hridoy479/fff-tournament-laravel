<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    protected $fillable = [
        'tournament_id',
        'user_id',
        'message',
        'is_system'
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    /**
     * Get the tournament that owns the chat message.
     */
    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * Get the user that sent the chat message.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
