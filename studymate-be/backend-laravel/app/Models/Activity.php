<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    protected $fillable = ['id', 'actor_id', 'type', 'message'];
    public $incrementing = false;
    protected $keyType = 'string';

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected $appends = ['actorId'];

    public function getActorIdAttribute()
    {
        return $this->attributes['actor_id'] ?? null;
    }
}
