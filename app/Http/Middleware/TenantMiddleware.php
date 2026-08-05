<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        // A normal user can never choose another tenant. The tenant is derived
        // from the authenticated account. Only a super admin may explicitly
        // select a tenant through X-Tenant-Id.
        $tenantId = $user->tenant_id;

        if ($user->hasRole('super_admin')) {
            $tenantId = $request->header('X-Tenant-Id') ?: $tenantId;
        }

        if (! $tenantId) {
            return response()->json(['message' => 'Tenant non déterminé'], 400);
        }

        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            return response()->json(['message' => 'Tenant invalide'], 404);
        }

        if (! $user->hasRole('super_admin') && (int) $tenant->id !== (int) $user->tenant_id) {
            return response()->json(['message' => 'Accès à cette organisation interdit'], 403);
        }

        if (! $tenant->is_active) {
            return response()->json(['message' => 'Cette organisation est inactive'], 403);
        }

        if (
            $tenant->subscription_expires_at
            && $tenant->subscription_expires_at->isPast()
            && $tenant->subscription_plan !== 'enterprise'
        ) {
            return response()->json([
                'message' => 'L\'abonnement de cette organisation est expiré',
            ], 403);
        }

        app()->instance('tenant', $tenant);
        $request->merge(['tenant' => $tenant]);

        return $next($request);
    }
}
