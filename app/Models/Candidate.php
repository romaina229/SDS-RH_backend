<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Candidate extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'recruitment_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'cv_path',
        'cover_letter',
        'education',
        'experience',
        'skills',
        'status',
        'feedback',
        'expected_salary',
        'available_from',
    ];

    protected $casts = [
        'education' => 'array',
        'experience' => 'array',
        'skills' => 'array',
        'expected_salary' => 'decimal:2',
        'available_from' => 'date',
    ];

    public function recruitment(): BelongsTo
    {
        return $this->belongsTo(Recruitment::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
}
