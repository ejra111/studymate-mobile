<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $fillable = ['id', 'name', 'address', 'map_hint'];
    public $incrementing = false;
    protected $keyType = 'string';

    public function studyGroups()
    {
        return $this->hasMany(StudyGroup::class);
    }

    protected $appends = ['mapHint'];

    public function getMapHintAttribute()
    {
        return $this->attributes['map_hint'] ?? null;
    }
}
