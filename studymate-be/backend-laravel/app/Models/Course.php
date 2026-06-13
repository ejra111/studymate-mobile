<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = ['id', 'code', 'name', 'program_id'];
    public $incrementing = false;
    protected $keyType = 'string';

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function studyGroups()
    {
        return $this->hasMany(StudyGroup::class);
    }

    protected $appends = ['programId'];

    public function getProgramIdAttribute()
    {
        return $this->attributes['program_id'] ?? null;
    }
}
