<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'plan',
        'price',
        'currency',
        'billing_cycle',
        'start_date',
        'end_date',
        'status',
        'features',
        'payment_reference',
        'payment_method',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'features' => 'array',
    ];

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->end_date?->isFuture();
    }
}
