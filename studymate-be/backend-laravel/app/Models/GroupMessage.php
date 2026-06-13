<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupMessage extends Model
{
    protected $fillable = ['id', 'study_group_id', 'user_id', 'message'];
    public $incrementing = false;
    protected $keyType = 'string';

    public function group()
    {
        return $this->belongsTo(StudyGroup::class, 'study_group_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

