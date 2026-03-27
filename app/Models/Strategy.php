<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Strategy extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'timeframe',
        'setup_type',
        'risk_model'
    ];

    public function user()
    {
        return $this->belongTo(User::class);
    }

    public function trades()
    {
        return $this->hasMany(Trade::class);
    }
}
