<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Goal extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'employee_id',
        'title',
        'description',
        'category',
        'target',
        'progress',
        'start_date',
        'end_date',
        'priority',
        'status',
        'key_results',
    ];

    protected $casts = [
        'target' => 'decimal:2',
        'progress' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'key_results' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function getCompletionRateAttribute(): float
    {
        if (! $this->target || (float) $this->target === 0.0) {
            return 0.0;
        }
        return round(($this->progress / $this->target) * 100, 1);
    }
}
