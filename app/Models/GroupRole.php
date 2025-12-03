<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Group;
use App\Models\User;

class GroupRole extends Model
{
    use HasFactory;

    protected $table = 'group_roles';

    public function group(){
        return $this->hasOne(Group::class, 'id', 'group_id');
    }

    public function user(){
        return $this->hasOne(User::class, 'id', 'user_id');
    }
}
