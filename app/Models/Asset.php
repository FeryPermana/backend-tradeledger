<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    protected $fillable = [
        'user_id',
        'symbol',
        'name',
        'market',
        'category',
        'is_watchlist',
        'tradingview_url',
        'current_price',
        'price_updated_at',
    ];

    protected $casts = [
        'is_watchlist' => 'boolean',
        'current_price' => 'decimal:8',
        'price_updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function trades()
    {
        return $this->hasMany(Trade::class);
    }

    public function portfolioPositions()
    {
        return $this->hasMany(PortfolioPosition::class);
    }
}
