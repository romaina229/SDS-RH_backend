<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Employee extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'user_id',
        'employee_number',
        'department_id',
        'position_id',
        'hire_date',
        'birth_date',
        'gender',
        'marital_status',
        'children_count',
        'nationality',
        'emergency_contact',
        'emergency_phone',
        'bank_details',
        'social_security',
        'status',
        'terminated_at'
    ];

    protected $casts = [
        'hire_date' => 'date',
        'birth_date' => 'date',
        'terminated_at' => 'datetime',
        'bank_details' => 'array',
        'social_security' => 'array',
        'children_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function trainings(): BelongsToMany
    {
        return $this->belongsToMany(Training::class, 'training_participants')
                    ->withPivot('status', 'score', 'completion_date')
                    ->withTimestamps();
    }

    public function performanceReviews(): HasMany
    {
        return $this->hasMany(Performance::class, 'reviewer_id');
    }

    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    public function getFullNameAttribute()
    {
        return $this->user?->full_name ?? $this->employee_number;
    }

    public function getCurrentContractAttribute()
    {
        return $this->contracts()->where('status', 'active')->latest()->first();
    }

    public function getCurrentLeaveBalanceAttribute()
    {
        return $this->leaveBalances()->where('year', date('Y'))->first();
    }

    public function getMaritalStatusLabelAttribute(): ?string
    {
        return match ($this->marital_status) {
            'single' => 'Célibataire',
            'married' => 'Marié(e)',
            'divorced' => 'Divorcé(e)',
            'widowed' => 'Veuf/Veuve',
            default => null,
        };
    }

    public function getBankNameAttribute(): ?string
    {
        return $this->bank_details['bank_name'] ?? null;
    }

    public function getBankAccountNumberAttribute(): ?string
    {
        return $this->bank_details['account_number'] ?? null;
    }
}