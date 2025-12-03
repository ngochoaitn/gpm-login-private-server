<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Group;

class Profile extends Model
{
    use HasFactory;

    protected $table = 'profiles';

    public function lastRunUser(){
        return $this->hasOne(User::class, 'id', 'last_run_by');
    }

    public function createdUser(){
        return $this->hasOne(User::class, 'id', 'created_by');
    }

    public function group(){
        return $this->hasOne(Group::class, 'id', 'group_id');
    }
}
