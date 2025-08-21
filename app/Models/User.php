<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'role_id',
        'provider',
        'provider_id',
        'provider_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the role associated with the user.
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the wallet associated with the user.
     */
    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    /**
     * Get the transactions associated with the user.
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get the deposits associated with the user.
     */
    public function deposits()
    {
        return $this->hasMany(Deposit::class);
    }

    /**
     * Get the withdrawals associated with the user.
     */
    public function withdrawals()
    {
        return $this->hasMany(Withdrawal::class);
    }

    /**
     * Get the tournaments created by the user.
     */
    public function createdTournaments()
    {
        return $this->hasMany(Tournament::class, 'created_by');
    }

    /**
     * Get the tournaments the user has entered.
     */
    public function enteredTournaments()
    {
        return $this->hasMany(TournamentEntry::class);
    }

    /**
     * Get the matches where the user is in slot A.
     */
    public function matchesSlotA()
    {
        return $this->hasMany(Match::class, 'slot_a_user_id');
    }

    /**
     * Get the matches where the user is in slot B.
     */
    public function matchesSlotB()
    {
        return $this->hasMany(Match::class, 'slot_b_user_id');
    }

    /**
     * Get the matches won by the user.
     */
    public function matchesWon()
    {
        return $this->hasMany(Match::class, 'winner_user_id');
    }
}
