<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tournament extends Model
{
    protected $fillable = [
        'game_id', 'title', 'slug', 'banner', 'description', 'rules',
        'prize_pool', 'entry_fee', 'max_players', 'format', 'seeding_type',
        'status', 'reg_starts_at', 'reg_ends_at', 'starts_at', 'ends_at',
        'registration_deadline', 'created_by'
    ];

    protected $casts = [
        'reg_starts_at' => 'datetime',
        'reg_ends_at' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /**
     * The tournament formats.
     */
    const FORMAT_SINGLE_ELIMINATION = 'single_elimination';
    const FORMAT_DOUBLE_ELIMINATION = 'double_elimination';
    const FORMAT_ROUND_ROBIN = 'round_robin';
    const FORMAT_SWISS = 'swiss';

    /**
     * The tournament statuses.
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_UPCOMING = 'upcoming';
    const STATUS_REGISTRATION_OPEN = 'registration_open';
    const STATUS_REGISTRATION_CLOSED = 'registration_closed';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
// Removed duplicate constant definition

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(TournamentEntry::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class);
    }

    public function chats(): HasMany
    {
        return $this->hasMany(Chat::class);
    }

    /**
     * Scope a query to only include featured tournaments.
     * Featured tournaments are those with higher prize pools and are currently accepting registrations or in progress.
     */
    public function scopeFeatured($query)
    {
        return $query->where('prize_pool', '>', 0)
            ->whereIn('status', [
                self::STATUS_REGISTRATION_OPEN,
                self::STATUS_REGISTRATION_CLOSED,
                self::STATUS_IN_PROGRESS
            ])
            ->orderBy('prize_pool', 'desc');
    }

    /**
     * Scope a query to only include ongoing tournaments.
     * Ongoing tournaments are those currently in progress.
     */
    public function scopeOngoing($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS)
            ->orderBy('starts_at', 'desc');
    }
}
