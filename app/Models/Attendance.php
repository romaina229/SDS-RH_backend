<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'employee_id',
        'date',
        'clock_in',
        'clock_out',
        'break_start',
        'break_end',
        'total_hours',
        'overtime_hours',
        'status',
        'method',
        'qr_code',
        'notes',
        'location_data',
    ];

    protected $casts = [
        'date' => 'date',
        'total_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'location_data' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
