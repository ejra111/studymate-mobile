<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmergencyAlert extends Model
{
    protected $fillable = [
        'id',
        'meetup_id',
        'user_id',
        'latitude',
        'longitude',
        'alert_time',
    ];
    
    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'alert_time' => 'datetime',
    ];

    protected $appends = ['meetupId', 'userId'];

    public function meetup()
    {
        return $this->belongsTo(Meetup::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getMeetupIdAttribute()
    {
        return $this->attributes['meetup_id'] ?? null;
    }

    public function getUserIdAttribute()
    {
        return $this->attributes['user_id'] ?? null;
    }
}
