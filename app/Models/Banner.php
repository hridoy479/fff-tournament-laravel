<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = [
        'title',
        'image_url',
        'link_url',
        'active_from',
        'active_to',
        'is_active'
    ];

    protected $casts = [
        'active_from' => 'datetime',
        'active_to' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Scope a query to only include active banners.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('active_from')
                    ->orWhere('active_from', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('active_to')
                    ->orWhere('active_to', '>=', now());
            });
    }
}
