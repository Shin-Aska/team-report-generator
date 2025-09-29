<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusProject extends Model
{
    use HasFactory;

    protected $table = 'BusProject';

    protected $fillable = [
        'project_name',
        'project_description',
    ];
}
