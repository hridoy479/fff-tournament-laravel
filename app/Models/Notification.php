<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type',
        'data',
        'read_at'
    ];

    protected $casts = [
        'data' => 'json',
        'read_at' => 'datetime',
    ];

    /**
     * The notification types.
     */
    const TYPE_SYSTEM = 'system';
    const TYPE_TOURNAMENT = 'tournament';
    const TYPE_MATCH = 'match';
    const TYPE_TRANSACTION = 'transaction';

    /**
     * Get the user that owns the notification.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include unread notifications.
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Mark the notification as read.
     */
    public function markAsRead()
    {
        $this->update(['read_at' => now()]);
    }
}
