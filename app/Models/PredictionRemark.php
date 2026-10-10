<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PredictionRemark extends Model
{
    protected $fillable = ['user_id', 'author_name', 'barangay', 'body'];
}
