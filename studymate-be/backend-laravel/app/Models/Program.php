<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    protected $fillable = ['id', 'name', 'faculty'];
    public $incrementing = false;
    protected $keyType = 'string';

    public function courses()
    {
        return $this->hasMany(Course::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
