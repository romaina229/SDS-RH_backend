<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Leave extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'employee_id',
        'approved_by',
        'type',
        'start_date',
        'end_date',
        'days',
        'reason',
        'status',
        'rejection_reason',
        'approval_date',
        'attachment'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approval_date' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeCurrentYear($query)
    {
        return $query->whereYear('start_date', date('Y'));
    }
}