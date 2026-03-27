<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trade extends Model
{
    protected $fillable = [
        'user_id',
        'account_id',
        'asset_id',
        'strategy_id',
        'position_type',
        'entry_price',
        'exit_price',
        'quantity',
        'closed_quantity',
        'stop_loss',
        'take_profit',
        'fees',
        'profit_loss',
        'r_multiple',
        'entry_date',
        'exit_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'entry_date' => 'datetime',
        'exit_date' => 'datetime',
    ];

    protected $appends = [
        'remaining_quantity',
    ];

    public function getRemainingQuantityAttribute(): float
    {
        return max(0, (float) $this->quantity - (float) $this->closed_quantity);
    }

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

    public function strategy()
    {
        return $this->belongsTo(Strategy::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }
}
