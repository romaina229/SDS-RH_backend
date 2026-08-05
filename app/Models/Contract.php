<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contract extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'employee_id',
        'type',
        'status',
        'start_date',
        'end_date',
        'probation_end_date',
        'base_salary',
        'currency',
        'benefits',
        'terms',
        'contract_file',
        'termination_reason'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'probation_end_date' => 'date',
        'base_salary' => 'decimal:2',
        'benefits' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function getIsOnProbationAttribute()
    {
        if (!$this->probation_end_date) {
            return false;
        }
        return $this->probation_end_date->isFuture();
    }

    public function getDaysRemainingAttribute()
    {
        if (!$this->end_date) {
            return null;
        }
        return now()->diffInDays($this->end_date);
    }

    public function getIsExpiringSoonAttribute()
    {
        if (!$this->end_date) {
            return false;
        }
        return $this->days_remaining <= 30 && $this->status === 'active';
    }
}