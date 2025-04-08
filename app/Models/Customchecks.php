<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Customchecks extends Model
{
    protected $fillable = [
        'name',
        'description',
        'check_type',    
        'threshold_value',
        'comparison_operator', 
        'is_active',
        'alert_severity', 
        'notification_emails',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'notification_emails' => 'array',
        'threshold_value' => 'float',
    ];

    public function magentos(): BelongsToMany
    {
        return $this->belongsToMany(Magento::class, 'customcheck_magento');
    }
}