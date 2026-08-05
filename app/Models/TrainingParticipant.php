<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingParticipant extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'training_id',
        'employee_id',
        'status',
        'score',
        'feedback',
        'certificate_path',
        'completion_date',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'completion_date' => 'date',
    ];

    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
