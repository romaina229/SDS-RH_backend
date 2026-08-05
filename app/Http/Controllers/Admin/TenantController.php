<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    /**
     * Liste des organisations avec filtres
     */
    public function index(Request $request)
    {
        $query = Tenant::withCount(['users', 'employees']);

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%");
            });
        }

        if ($request->is_active !== null) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->subscription_plan) {
            $query->where('subscription_plan', $request->subscription_plan);
        }

        $tenants = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'data' => $tenants->items(),
            'current_page' => $tenants->currentPage(),
            'last_page' => $tenants->lastPage(),
            'per_page' => $tenants->perPage(),
            'total' => $tenants->total(),
        ]);
    }

    /**
     * Créer une nouvelle organisation
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:tenants,email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'subscription_plan' => 'required|in:gratuit,starter,standard,business,enterprise',
            'subscription_expires_at' => 'nullable|date|after:today',
            'is_active' => 'boolean',
            'admin_first_name' => 'required|string|max:255',
            'admin_last_name' => 'required|string|max:255',
            'admin_email' => 'required|email|unique:users,email',
            'admin_password' => 'required|string|min:8',
            'admin_phone' => 'nullable|string|max:20',
        ]);

        DB::beginTransaction();

        try {
            // Créer le tenant
            $tenant = Tenant::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'subscription_plan' => $request->subscription_plan,
                'subscription_expires_at' => $request->subscription_expires_at,
                'is_active' => $request->is_active ?? true,
                'settings' => [
                    'language' => 'fr',
                    'currency' => 'XOF',
                    'timezone' => 'Africa/Porto-Novo',
                    'country' => 'BJ',
                ],
            ]);

            // Créer l'utilisateur administrateur
            $user = User::create([
                'tenant_id' => $tenant->id,
                'first_name' => $request->admin_first_name,
                'last_name' => $request->admin_last_name,
                'email' => $request->admin_email,
                'password' => Hash::make($request->admin_password),
                'phone' => $request->admin_phone,
                'status' => 'active',
            ]);

            $user->assignRole('admin_org');

            // Créer le profil employé de l'admin
            Employee::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'employee_number' => 'EMP-' . str_pad($tenant->id, 5, '0', STR_PAD_LEFT) . '-001',
                'hire_date' => now(),
                'status' => 'active',
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Organisation créée avec succès',
                'tenant' => $tenant->load(['users', 'employees']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Une erreur interne est survenue lors de la création.',
            ], 500);
        }
    }

    /**
     * Afficher les détails d'une organisation
     */
    public function show(Tenant $tenant)
    {
        $tenant->load([
            'users' => function($query) {
                $query->with('employee');
            },
            'employees' => function($query) {
                $query->with(['user', 'department', 'position']);
            },
            'subscriptions' => function($query) {
                $query->orderBy('created_at', 'desc');
            },
            'departments',
        ]);

        // Statistiques supplémentaires
        $stats = [
            'total_employees' => $tenant->employees()->count(),
            'active_employees' => $tenant->employees()->where('status', 'active')->count(),
            'total_users' => $tenant->users()->count(),
            'active_users' => $tenant->users()->where('status', 'active')->count(),
            'total_departments' => $tenant->departments()->count(),
            'total_contracts' => $tenant->contracts()->count(),
            'active_contracts' => $tenant->contracts()->where('status', 'active')->count(),
        ];

        return response()->json([
            'tenant' => $tenant,
            'stats' => $stats,
        ]);
    }

    /**
     * Mettre à jour une organisation
     */
    public function update(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('tenants')->ignore($tenant->id)],
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'subscription_plan' => 'sometimes|in:gratuit,starter,standard,business,enterprise',
            'subscription_expires_at' => 'nullable|date|after:today',
            'is_active' => 'boolean',
            'settings' => 'nullable|array',
            'settings.language' => 'nullable|in:fr,en',
            'settings.currency' => 'nullable|in:XOF,EUR,USD',
            'settings.timezone' => 'nullable|string',
            'settings.country' => 'nullable|string|size:2',
        ]);

        $tenant->update($data);

        return response()->json([
            'message' => 'Organisation mise à jour avec succès',
            'tenant' => $tenant->fresh(),
        ]);
    }

    /**
     * Supprimer une organisation
     */
    public function destroy(Tenant $tenant)
    {
        // Vérifier s'il y a des données associées
        $employeeCount = $tenant->employees()->count();
        $userCount = $tenant->users()->count();

        if ($employeeCount > 0 || $userCount > 0) {
            return response()->json([
                'message' => 'Impossible de supprimer cette organisation car elle contient des données',
                'employee_count' => $employeeCount,
                'user_count' => $userCount,
            ], 422);
        }

        $tenant->delete();

        return response()->json([
            'message' => 'Organisation supprimée avec succès',
        ]);
    }

    /**
     * Activer une organisation
     */
    public function activate(Tenant $tenant)
    {
        $tenant->update(['is_active' => true]);

        return response()->json([
            'message' => 'Organisation activée avec succès',
            'tenant' => $tenant,
        ]);
    }

    /**
     * Désactiver une organisation
     */
    public function deactivate(Tenant $tenant)
    {
        $tenant->update(['is_active' => false]);

        return response()->json([
            'message' => 'Organisation désactivée avec succès',
            'tenant' => $tenant,
        ]);
    }

    /**
     * Statistiques globales des organisations
     */
    public function stats()
    {
        $stats = [
            'total' => Tenant::count(),
            'active' => Tenant::where('is_active', true)->count(),
            'inactive' => Tenant::where('is_active', false)->count(),
            'by_plan' => Tenant::select('subscription_plan', DB::raw('COUNT(*) as count'))
                ->groupBy('subscription_plan')
                ->get()
                ->map(function($item) {
                    return [
                        'plan' => $item->subscription_plan,
                        'count' => $item->count,
                    ];
                }),
            'total_users' => User::count(),
            'total_employees' => Employee::count(),
            'active_employees' => Employee::where('status', 'active')->count(),
            'total_tenants_created_last_30_days' => Tenant::where('created_at', '>=', now()->subDays(30))->count(),
        ];

        // Évolution des créations par mois (6 derniers mois)
        $monthlyGrowth = Tenant::select(
                DB::raw('DATE_FORMAT(created_at, "%b %Y") as month'),
                DB::raw('COUNT(*) as count')
            )
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('created_at')
            ->get();

        $stats['monthly_growth'] = $monthlyGrowth;

        return response()->json($stats);
    }

    /**
     * Statistiques détaillées d'une organisation spécifique
     */
    public function tenantStats(Tenant $tenant)
    {
        $stats = [
            'employees' => [
                'total' => $tenant->employees()->count(),
                'active' => $tenant->employees()->where('status', 'active')->count(),
                'on_leave' => $tenant->employees()->where('status', 'on_leave')->count(),
                'terminated' => $tenant->employees()->where('status', 'terminated')->count(),
                'suspended' => $tenant->employees()->where('status', 'suspended')->count(),
            ],
            'contracts' => [
                'total' => $tenant->contracts()->count(),
                'active' => $tenant->contracts()->where('status', 'active')->count(),
                'expired' => $tenant->contracts()->where('status', 'expired')->count(),
                'terminated' => $tenant->contracts()->where('status', 'terminated')->count(),
                'pending' => $tenant->contracts()->where('status', 'pending')->count(),
            ],
            'attendance' => [
                'today' => $tenant->attendances()->where('date', now()->toDateString())->count(),
                'present_today' => $tenant->attendances()
                    ->where('date', now()->toDateString())
                    ->where('status', 'present')
                    ->count(),
                'absent_today' => $tenant->attendances()
                    ->where('date', now()->toDateString())
                    ->where('status', 'absent')
                    ->count(),
            ],
            'leaves' => [
                'pending' => $tenant->leaves()->where('status', 'pending')->count(),
                'approved' => $tenant->leaves()->where('status', 'approved')->count(),
                'rejected' => $tenant->leaves()->where('status', 'rejected')->count(),
                'cancelled' => $tenant->leaves()->where('status', 'cancelled')->count(),
            ],
            'payroll' => [
                'total_net' => $tenant->payrolls()
                    ->where('month', now()->format('Y-m'))
                    ->sum('net_salary'),
                'total_gross' => $tenant->payrolls()
                    ->where('month', now()->format('Y-m'))
                    ->sum('base_salary'),
                'processed' => $tenant->payrolls()
                    ->where('month', now()->format('Y-m'))
                    ->where('status', 'processed')
                    ->count(),
                'paid' => $tenant->payrolls()
                    ->where('month', now()->format('Y-m'))
                    ->where('status', 'paid')
                    ->count(),
            ],
            'recruitments' => [
                'total' => $tenant->recruitments()->count(),
                'published' => $tenant->recruitments()->where('status', 'published')->count(),
                'closed' => $tenant->recruitments()->where('status', 'closed')->count(),
                'cancelled' => $tenant->recruitments()->where('status', 'cancelled')->count(),
            ],
            'trainings' => [
                'total' => $tenant->trainings()->count(),
                'planned' => $tenant->trainings()->where('status', 'planned')->count(),
                'ongoing' => $tenant->trainings()->where('status', 'ongoing')->count(),
                'completed' => $tenant->trainings()->where('status', 'completed')->count(),
                'cancelled' => $tenant->trainings()->where('status', 'cancelled')->count(),
            ],
        ];

        return response()->json([
            'tenant' => $tenant->only(['id', 'name', 'email', 'subscription_plan', 'is_active']),
            'stats' => $stats,
        ]);
    }

    /**
     * Mettre à jour l'abonnement d'une organisation
     */
    public function updateSubscription(Request $request, Tenant $tenant)
    {
        $request->validate([
            'subscription_plan' => 'required|in:gratuit,starter,standard,business,enterprise',
            'subscription_expires_at' => 'nullable|date|after:today',
        ]);

        $tenant->update([
            'subscription_plan' => $request->subscription_plan,
            'subscription_expires_at' => $request->subscription_expires_at,
        ]);

        return response()->json([
            'message' => 'Abonnement mis à jour avec succès',
            'tenant' => $tenant->fresh(),
        ]);
    }

    /**
     * Exporter la liste des organisations
     */
    public function export(Request $request)
    {
        $tenants = Tenant::withCount(['users', 'employees'])
            ->orderBy('created_at', 'desc')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="organisations_' . date('Y-m-d') . '.csv"',
        ];

        $callback = function() use ($tenants) {
            $file = fopen('php://output', 'w');
            
            // Entête CSV
            fputcsv($file, [
                'ID', 'Nom', 'Email', 'Téléphone', 'Plan', 'Statut', 
                'Nombre d\'utilisateurs', 'Nombre d\'employés', 
                'Créé le', 'Expire le'
            ]);

            foreach ($tenants as $tenant) {
                fputcsv($file, [
                    $tenant->id,
                    $tenant->name,
                    $tenant->email,
                    $tenant->phone,
                    $tenant->subscription_plan,
                    $tenant->is_active ? 'Actif' : 'Inactif',
                    $tenant->users_count,
                    $tenant->employees_count,
                    $tenant->created_at->format('d/m/Y H:i'),
                    $tenant->subscription_expires_at ? date('d/m/Y', strtotime($tenant->subscription_expires_at)) : '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Recherche rapide d'organisations
     */
    public function search(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2',
        ]);

        $tenants = Tenant::where('name', 'like', "%{$request->q}%")
            ->orWhere('email', 'like', "%{$request->q}%")
            ->orWhere('phone', 'like', "%{$request->q}%")
            ->limit(20)
            ->get(['id', 'name', 'email', 'phone', 'subscription_plan', 'is_active']);

        return response()->json([
            'data' => $tenants,
        ]);
    }
}