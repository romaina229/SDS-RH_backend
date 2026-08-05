<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'employee_id',
        'year',
        'annual_entitled',
        'annual_taken',
        'annual_remaining',
        'sick_entitled',
        'sick_taken',
        'sick_remaining',
    ];

    protected $casts = [
        'year' => 'integer',
        'annual_entitled' => 'decimal:2',
        'annual_taken' => 'decimal:2',
        'annual_remaining' => 'decimal:2',
        'sick_entitled' => 'decimal:2',
        'sick_taken' => 'decimal:2',
        'sick_remaining' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
