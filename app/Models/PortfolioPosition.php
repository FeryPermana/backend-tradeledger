<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortfolioPosition extends Model
{
    protected $fillable = [
        'user_id',
        'account_id',
        'asset_id',
        'quantity',
        'avg_price',
        'total_fees',
        'target_price',
        'horizon',
        'conviction_level',
        'thesis',
        'notes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
}
