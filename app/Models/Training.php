<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Training extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'title',
        'description',
        'type',
        'start_date',
        'end_date',
        'location',
        'trainer',
        'cost',
        'max_participants',
        'status',
        'objectives',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'cost' => 'decimal:2',
        'max_participants' => 'integer',
        'objectives' => 'array',
    ];

    public function participants(): HasMany
    {
        return $this->hasMany(TrainingParticipant::class);
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'training_participants')
                    ->withPivot('status', 'score', 'completion_date')
                    ->withTimestamps();
    }
}
