<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'employee_id',
        'name',
        'type',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'uploaded_by',
        'expiry_date',
        'is_confidential',
        'metadata',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'is_confidential' => 'boolean',
        'metadata' => 'array',
        'file_size' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
