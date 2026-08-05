<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Performance extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'employee_id',
        'reviewer_id',
        'period',
        'ratings',
        'strengths',
        'weaknesses',
        'achievements',
        'goals_achieved',
        'recommendations',
        'overall_score',
        'status',
        'review_date',
    ];

    protected $casts = [
        'ratings' => 'array',
        'overall_score' => 'decimal:2',
        'review_date' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    // reviewer_id référence employees.id (voir migration), pas users.id
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewer_id');
    }
}
