<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use App\Models\Department;
use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = Employee::with(['user', 'department', 'position']);

        // Filtres
        if ($request->department_id) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('employee_number', 'like', "%{$request->search}%")
                  ->orWhereHas('user', function($u) use ($request) {
                      $u->where('first_name', 'like', "%{$request->search}%")
                        ->orWhere('last_name', 'like', "%{$request->search}%")
                        ->orWhere('email', 'like', "%{$request->search}%");
                  });
            });
        }

        $employees = $query->paginate($request->per_page ?? 15);

        return response()->json($employees);
    }

    public function store(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'department_id' => 'nullable|exists:departments,id',
            'position_id' => 'nullable|exists:positions,id',
            'hire_date' => 'required|date',
            'birth_date' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'marital_status' => 'nullable|in:single,married,divorced,widowed',
            'children_count' => 'nullable|integer|min:0|max:30',
            'nationality' => 'nullable|string|max:100',
            'emergency_contact' => 'nullable|string|max:255',
            'emergency_phone' => 'nullable|string|max:20',
            'bank_details' => 'nullable|array',
            'bank_details.bank_name' => 'nullable|string|max:255',
            'bank_details.account_number' => 'nullable|string|max:100',
            'social_security' => 'nullable|array',
        ]);

        $tenant = app('tenant');

        DB::beginTransaction();

        try {
            // Créer l'utilisateur (mot de passe temporaire aléatoire : l'employé
            // doit définir le sien via un lien d'invitation, jamais de mot de
            // passe par défaut prévisible envoyé en clair dans le code).
            $temporaryPassword = \Illuminate\Support\Str::random(40);

            $user = User::create([
                'tenant_id' => $tenant->id,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'password' => Hash::make($temporaryPassword),
                'phone' => $request->phone,
                'status' => 'active',
            ]);

            $user->assignRole('employee');

            // The temporary password is never exposed. An invitation/reset link
            // should be sent by the mail system after the transaction commits.

            // Créer l'employé
            $employee = Employee::create([
                'user_id' => $user->id,
                'employee_number' => $this->generateEmployeeNumber($tenant->id),
                'department_id' => $request->department_id,
                'position_id' => $request->position_id,
                'hire_date' => $request->hire_date,
                'birth_date' => $request->birth_date,
                'gender' => $request->gender,
                'marital_status' => $request->marital_status,
                'children_count' => $request->children_count,
                'nationality' => $request->nationality,
                'emergency_contact' => $request->emergency_contact,
                'emergency_phone' => $request->emergency_phone,
                'bank_details' => $request->bank_details,
                'social_security' => $request->social_security,
                'status' => 'active',
            ]);

            // Créer un solde de congé
            $this->createLeaveBalance($employee);

            DB::commit();

            // Send a password setup link when mail is configured. The employee
            // account is already active, but the generated password is unknown
            // to the administrator and is never returned by the API.
            try {
                Password::sendResetLink(['email' => $user->email]);
            } catch (\Throwable $mailError) {
                report($mailError);
            }

            return response()->json([
                'message' => 'Employé créé avec succès',
                'employee' => $employee->load(['user', 'department', 'position'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            report($e); // journalise la vraie erreur dans storage/logs/laravel.log
            return response()->json([
                'message' => 'Une erreur interne est survenue lors de la création.'
            ], 500);
        }
    }

    public function show(Employee $employee)
    {
        return response()->json([
            'employee' => $employee->load([
                'user',
                'department',
                'position',
                'contracts' => function($query) {
                    $query->latest();
                },
                'documents',
                'leaveBalances' => function($query) {
                    $query->where('year', date('Y'));
                }
            ])
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($employee->user_id)],
            'phone' => 'nullable|string|max:20',
            'department_id' => [
                'nullable',
                Rule::exists('departments', 'id')->where(fn ($q) => $q->where('tenant_id', app('tenant')->id)),
            ],
            'position_id' => [
                'nullable',
                Rule::exists('positions', 'id')->where(fn ($q) => $q->where('tenant_id', app('tenant')->id)),
            ],
            'hire_date' => 'sometimes|date',
            'birth_date' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'marital_status' => 'nullable|in:single,married,divorced,widowed',
            'children_count' => 'nullable|integer|min:0|max:30',
            'bank_details' => 'nullable|array',
            'bank_details.bank_name' => 'nullable|string|max:255',
            'bank_details.account_number' => 'nullable|string|max:100',
            'nationality' => 'nullable|string|max:100',
            'emergency_contact' => 'nullable|string|max:255',
            'emergency_phone' => 'nullable|string|max:20',
            'bank_details' => 'nullable|array',
            'social_security' => 'nullable|array',
            'status' => 'sometimes|in:active,on_leave,terminated,suspended',
        ]);

        DB::beginTransaction();

        try {
            // Mettre à jour l'utilisateur
            if ($request->has('first_name') || $request->has('last_name') || 
                $request->has('email') || $request->has('phone')) {
                $employee->user->update([
                    'first_name' => $request->first_name ?? $employee->user->first_name,
                    'last_name' => $request->last_name ?? $employee->user->last_name,
                    'email' => $request->email ?? $employee->user->email,
                    'phone' => $request->phone ?? $employee->user->phone,
                ]);
            }

            // Mettre à jour l'employé
            $employee->update($request->except(['first_name', 'last_name', 'email', 'phone']));

            DB::commit();

            return response()->json([
                'message' => 'Employé mis à jour avec succès',
                'employee' => $employee->fresh()->load(['user', 'department', 'position'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return response()->json([
                'message' => 'Une erreur interne est survenue lors de la mise à jour.'
            ], 500);
        }
    }

    public function destroy(Employee $employee)
    {
        DB::beginTransaction();

        try {
            // Désactiver l'utilisateur
            $employee->user->update(['status' => 'inactive']);
            
            // Mettre à jour le statut de l'employé
            $employee->update([
                'status' => 'terminated',
                'terminated_at' => now()
            ]);

            // Désactiver les contrats actifs
            $employee->contracts()->where('status', 'active')->update([
                'status' => 'terminated',
                'termination_reason' => 'Employé terminé'
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Employé terminé avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return response()->json([
                'message' => 'Une erreur interne est survenue lors de la suppression.'
            ], 500);
        }
    }

    public function stats()
    {
        $tenant = app('tenant');

        $stats = [
            'total' => Employee::where('status', 'active')->count(),
            'by_department' => Department::withCount(['employees' => function($query) {
                $query->where('status', 'active');
            }])->get(),
            'by_gender' => Employee::where('status', 'active')
                ->select('gender', DB::raw('COUNT(*) as count'))
                ->groupBy('gender')
                ->get(),
            'by_status' => Employee::select('status', DB::raw('COUNT(*) as count'))
                ->groupBy('status')
                ->get(),
        ];

        return response()->json($stats);
    }

    private function generateEmployeeNumber($tenantId)
    {
        $count = Employee::where('tenant_id', $tenantId)->count() + 1;
        return 'EMP-' . str_pad($tenantId, 5, '0', STR_PAD_LEFT) . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    private function createLeaveBalance($employee)
    {
        $year = date('Y');

        $annualDays = 24; // 2 jours ouvrables acquis par mois au Bénin, sauf dispositions plus favorables.

        return \App\Models\LeaveBalance::create([
            'employee_id' => $employee->id,
            'year' => $year,
            'annual_entitled' => $annualDays,
            'annual_taken' => 0,
            'annual_remaining' => $annualDays,
            'sick_entitled' => 10,
            'sick_taken' => 0,
            'sick_remaining' => 10,
        ]);
    }
}