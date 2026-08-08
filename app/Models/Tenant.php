<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'emitting_authority',
        'email',
        'phone',
        'fax',
        'website',
        'address',
        'logo',
        'ifu',
        'rccm',
        'subscription_plan',
        'subscription_expires_at',
        'is_active',
        'settings'
    ];

    protected $casts = [
        'settings' => 'array',
        'subscription_expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
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

    public function recruitments(): HasMany
    {
        return $this->hasMany(Recruitment::class);
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(Training::class);
    }

    public function performances(): HasMany
    {
        return $this->hasMany(Performance::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function getCurrentSubscriptionAttribute()
    {
        return $this->subscriptions()->where('status', 'active')->latest()->first();
    }

    public function isSubscriptionActive(): bool
    {
        return $this->subscription_expires_at &&
               $this->subscription_expires_at->isFuture() &&
               $this->is_active;
    }

    public function getPayslipHeaderNameAttribute(): string
    {
        return $this->emitting_authority ?: $this->name;
    }
}