<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudyGroup extends Model
{
    protected $fillable = [
        'id', 'title', 'topic', 'description', 'schedule', 
        'course_id', 'location_id', 'capacity', 'owner_id', 'status'
    ];
    public $incrementing = false;
    protected $keyType = 'string';

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'group_user', 'study_group_id', 'user_id');
    }

    public function messages()
    {
        return $this->hasMany(GroupMessage::class, 'study_group_id')->latest();
    }

    protected $appends = ['courseId', 'locationId', 'ownerId', 'memberIds'];

    public function getCourseIdAttribute()
    {
        return $this->attributes['course_id'] ?? null;
    }

    public function getLocationIdAttribute()
    {
        return $this->attributes['location_id'] ?? null;
    }

    public function getOwnerIdAttribute()
    {
        return $this->attributes['owner_id'] ?? null;
    }

    public function getMemberIdsAttribute()
    {
        return $this->members->pluck('id');
    }
}
