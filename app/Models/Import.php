<?php

namespace App\Models;

use App\Enums\ImportStatusEnum;
use Illuminate\Database\Eloquent\Model;

class Import extends Model
{
    protected $table = 'imports';

    protected $fillable = [
        'file_name',
        'file_hash',
        'status',
        'row_errors',
    ];

    protected $casts = [
        'status' => ImportStatusEnum::class,
        'row_errors' => 'array',
    ];
}
