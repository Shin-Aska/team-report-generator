<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Persist a dashboard bus project and its optional description.
 *
 * The legacy schema uses the explicit `BusProject` table name. Monthly selection and prompt formatting belong to `BusProjectService`.
 *
 * @property int $id
 * @property string $project_name
 * @property string|null $project_description
 */
class BusProject extends Model
{
    use HasFactory;

    protected $table = 'BusProject';

    protected $fillable = [
        'project_name',
        'project_description',
    ];
}
