<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfilePhoto extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['image_data'];
}
