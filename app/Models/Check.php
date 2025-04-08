<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Check extends Model
{
    protected $fillable = [
        'magento_id',
        'url_type',
        'status',
        'checked_at',
    ];

    protected $casts = [
        'checked_at' => 'datetime',
    ];

    public function magento(): BelongsTo
    {
        return $this->belongsTo(Magento::class);
    }
}