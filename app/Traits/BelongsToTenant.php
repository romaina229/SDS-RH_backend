<?php

namespace App\Traits;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A appliquer à tout modèle possédant une colonne tenant_id.
 *
 * - Filtre automatiquement toutes les requêtes (SELECT/UPDATE/DELETE) sur le
 *   tenant courant (résolu par TenantMiddleware et stocké dans app('tenant')).
 * - Renseigne automatiquement tenant_id à la création, SANS jamais faire
 *   confiance à une valeur envoyée par le client (tenant_id est volontairement
 *   RETIRÉ de $fillable sur les modèles qui utilisent ce trait).
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (app()->has('tenant')) {
                $builder->where($builder->getModel()->getTable() . '.tenant_id', app('tenant')->id);
            }
        });

        static::creating(function ($model) {
            if (! $model->tenant_id && app()->has('tenant')) {
                $model->tenant_id = app('tenant')->id;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Permet d'accéder à un enregistrement d'un autre tenant quand c'est
     * volontaire et justifié (ex : le Super Administrateur SDS-RH).
     * A utiliser explicitement, jamais par défaut.
     */
    public function scopeWithoutTenantScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }
}
