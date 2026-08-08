<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payroll extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'qr_token',
        'employee_id',
        'month',
        'worked_days',
        'hourly_rate',
        'base_salary',
        'overtime_hours',
        'overtime_amount',
        'bonuses',
        'deductions',
        'taxes',
        'social_security',
        'net_salary',
        'breakdown',
        'pay_slip_url',
        'payment_method',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'worked_days' => 'integer',
        'hourly_rate' => 'decimal:2',
        'base_salary' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'overtime_amount' => 'decimal:2',
        'bonuses' => 'decimal:2',
        'deductions' => 'decimal:2',
        'taxes' => 'decimal:2',
        'social_security' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'breakdown' => 'array',
        'paid_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'Brouillon',
            'processed' => 'En attente',
            'paid' => $this->paid_at ? 'Payé le ' . $this->paid_at->format('d/m/Y') : 'Payé',
            default => $this->status,
        };
    }
}