<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'name',
        'date',
        'country',
        'type',
        'is_recurring',
    ];

    protected $casts = [
        'date' => 'date',
        'is_recurring' => 'boolean',
    ];
}
