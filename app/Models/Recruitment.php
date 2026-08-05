<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recruitment extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'position_id',
        'title',
        'description',
        'requirements',
        'number_of_positions',
        'posted_date',
        'closing_date',
        'status',
        'interview_stages',
        'posting_url',
    ];

    protected $casts = [
        'posted_date' => 'date',
        'closing_date' => 'date',
        'interview_stages' => 'array',
        'number_of_positions' => 'integer',
    ];

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }
}
