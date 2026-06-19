<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Meetup extends Model
{
    protected $fillable = [
        'id',
        'creator_id',
        'study_group_id',
        'title',
        'description',
        'meeting_date',
        'meeting_time',
        'estimated_duration',
        'latitude',
        'longitude',
        'location_name',
        'status',
    ];
    
    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'meeting_date' => 'datetime',
    ];

    protected $appends = ['creatorId', 'studyGroupId'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function studyGroup()
    {
        return $this->belongsTo(StudyGroup::class);
    }

    public function participants()
    {
        return $this->hasMany(MeetupParticipant::class);
    }

    public function locations()
    {
        return $this->hasMany(MeetupLocation::class);
    }

    public function checkins()
    {
        return $this->hasMany(MeetupCheckin::class);
    }

    public function emergencyAlerts()
    {
        return $this->hasMany(EmergencyAlert::class);
    }

    public function getCreatorIdAttribute()
    {
        return $this->attributes['creator_id'] ?? null;
    }

    public function getStudyGroupIdAttribute()
    {
        return $this->attributes['study_group_id'] ?? null;
    }
}
