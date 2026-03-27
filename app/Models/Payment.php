<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
     protected $fillable = [
        'user_id',
        'plan',
        'amount',
        'method',
        'proof_image',
        'status',
        'paid_at',
        'reviewed_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
