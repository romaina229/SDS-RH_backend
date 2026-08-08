<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'name',
        'code',
        'description',
        'manager_id',
        'parent_department_id',
        'hierarchy_path',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_department_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_department_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    public function getEmployeeCountAttribute()
    {
        return $this->employees()->where('status', 'active')->count();
    }

    public function getFullHierarchyAttribute(): string
    {
        if (!empty($this->hierarchy_path)) {
            return $this->hierarchy_path;
        }

        $chain = [$this->name];
        $node = $this->parent;
        $guard = 0;

        while ($node && $guard < 10) {
            array_unshift($chain, $node->name);
            $node = $node->parent;
            $guard++;
        }

        return implode(' > ', $chain);
    }
}