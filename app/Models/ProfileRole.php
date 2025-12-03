<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Profile;
use App\Models\User;

class ProfileRole extends Model
{
    use HasFactory;

    protected $table = 'profile_roles';

    public function profile(){
        return $this->hasOne(Profile::class, 'id', 'profile_id');
    }

    public function user(){
        return $this->hasOne(User::class, 'id', 'user_id');
    }
}
