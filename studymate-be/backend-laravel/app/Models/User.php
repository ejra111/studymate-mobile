<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'name',
        'email',
        'university',
        'program_name',
        'avatar_url',
        'password',
        'role',
        'student_id',
        'program_id',
        'semester',
        'bio',
        'interests',
        'availability',
        'avatar_color',
        'verification_status',
        'ktm_image',
        'ktm_verification_date',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'interests' => 'array',
            'availability' => 'array',
        ];
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function courses()
    {
        return $this->belongsToMany(Course::class);
    }

    public function studyGroups()
    {
        return $this->belongsToMany(StudyGroup::class, 'group_user', 'user_id', 'study_group_id');
    }

    public function ownedGroups()
    {
        return $this->hasMany(StudyGroup::class, 'owner_id');
    }

    public function friends()
    {
        return $this->belongsToMany(User::class, 'friends', 'user_id', 'friend_id');
    }

    public function notificationsReceived()
    {
        return $this->hasMany(StudyNotification::class, 'receiver_id');
    }

    public function notificationsSent()
    {
        return $this->hasMany(StudyNotification::class, 'sender_id');
    }

    protected $appends = ['studentId', 'programId', 'avatarColor', 'avatarUrl', 'courseIds', 'verificationStatus', 'ktmVerificationDate'];

    public function getStudentIdAttribute()
    {
        return $this->attributes['student_id'] ?? null;
    }

    public function getProgramIdAttribute()
    {
        return $this->attributes['program_id'] ?? null;
    }

    public function getAvatarColorAttribute()
    {
        return $this->attributes['avatar_color'] ?? null;
    }

    public function getAvatarUrlAttribute()
    {
        $path = $this->attributes['avatar_url'] ?? null;
        if (!$path) return null;
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) return $path;
        $base = null;
        try {
            $base = request()->getSchemeAndHttpHost();
        } catch (\Throwable $e) {
            $base = null;
        }
        if (!$base) $base = config('app.url');
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }

    public function getCourseIdsAttribute()
    {
        return $this->courses->pluck('id');
    }

    public function getVerificationStatusAttribute()
    {
        return $this->attributes['verification_status'] ?? 'unverified';
    }



    public function getKtmVerificationDateAttribute()
    {
        return $this->attributes['ktm_verification_date'] ?? null;
    }

    public function createdMeetups()
    {
        return $this->hasMany(Meetup::class, 'creator_id');
    }

    public function meetupParticipants()
    {
        return $this->hasMany(MeetupParticipant::class);
    }

    public function meetupLocations()
    {
        return $this->hasMany(MeetupLocation::class);
    }

    public function meetupCheckins()
    {
        return $this->hasMany(MeetupCheckin::class);
    }

    public function emergencyAlerts()
    {
        return $this->hasMany(EmergencyAlert::class);
    }

    public function favoriteGroups()
    {
        return $this->belongsToMany(StudyGroup::class, 'favorite_groups', 'user_id', 'study_group_id')->withTimestamps();
    }
}
