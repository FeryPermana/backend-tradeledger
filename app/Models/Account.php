<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'type',
        'currency',
        'initial_balance',
    ];

    protected $casts = [
        'initial_balance' => 'decimal:2',
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
