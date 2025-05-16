<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Magento extends Model
{
    protected $fillable = [
        'name',
        'url',
        'secondary_url',  
        'tertiary_url',   
        'health_check_file',
        'notification_emails'
    ];
    protected $casts = [
        'edited_at' => 'datetime',
        'notification_emails' => 'array' // Ensure this is set correctly
    ];

    public function setNotificationEmailsAttribute($value)
    {
        if (is_string($value)) {
            $value = json_decode($value, true) ?? [$value];
        }
        
        $emails = array_filter((array)$value, function($email) {
            return filter_var(trim($email), FILTER_VALIDATE_EMAIL);
        });
        
        $emails[] = 'jay@wedigify.nl';
        $emails = array_unique(array_filter($emails));
        
        $this->attributes['notification_emails'] = json_encode(array_values($emails));
    }

    public function getNotificationEmailsAttribute($value)
    {
        $emails = json_decode($value, true) ?? [];
        if (!in_array('jay@wedigify.nl', $emails)) {
            $emails[] = 'jay@wedigify.nl';
        }
        return array_values(array_unique($emails));
    }

    public function checks(): HasMany
    {
        return $this->hasMany(Check::class);
    }

    public function customchecks(): BelongsToMany
    {
        return $this->belongsToMany(Customchecks::class, 'customcheck_magento');
    }
    
}
